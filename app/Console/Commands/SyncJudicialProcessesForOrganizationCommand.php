<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Src\Application\Shared\Jobs\SyncProcessJob;
use Src\Application\Shared\Services\Notification\DispatchOrganizationSyncDigest;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;

class SyncJudicialProcessesForOrganizationCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'judicial:sync-processes-for-organization
                            {organization : Organization UUID}
                            {--radicado= : Optional radicado (process_number) to sync only that radicado}';

    /**
     * @var string
     */
    protected $description = 'Sync one organization\'s Judicial Branch processes and send only that organization\'s digest';

    public function handle(): int
    {
        $organization = $this->resolveTrackableOrganization();

        if (! $organization instanceof Organization) {
            return self::FAILURE;
        }

        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');
        $radicadoFilter = $this->radicadoFilter();

        if ($radicadoFilter === null && JudicialSyncRun::hasActiveBatch(
            JudicialSyncDataSource::JudicialBranch,
            $organization->id,
        )) {
            $this->warn('Ya hay un sync activo para esta organización; no se encola otro lote.');
            Log::channel($channel)->warning('SyncJudicialProcessesForOrganizationCommand: skipped overlapping batch', [
                'organization_id' => $organization->id,
            ]);

            return self::SUCCESS;
        }

        $run = JudicialSyncRun::startRun($radicadoFilter, JudicialSyncDataSource::JudicialBranch, $organization->id);

        Log::channel($channel)->info('SyncJudicialProcessesForOrganizationCommand started', [
            'run_id' => $run->id,
            'organization_id' => $organization->id,
            'data_source' => JudicialSyncDataSource::JudicialBranch->value,
            'radicado_filter' => $radicadoFilter,
        ]);

        $processNumbers = Process::query()
            ->forJudicialDailySyncOfOrganization($organization->id, $radicadoFilter)
            ->pluck('processes.process_number');

        if ($processNumbers->isEmpty()) {
            return $this->finishWithoutProcesses($run, $channel);
        }

        return $this->dispatchBatch($run, $organization->id, $processNumbers, $channel);
    }

    private function resolveTrackableOrganization(): ?Organization
    {
        $organizationId = (string) $this->argument('organization');

        if (! Str::isUuid($organizationId)) {
            $this->error('The organization argument must be a UUID.');

            return null;
        }

        $organization = Organization::query()->find($organizationId);

        if (! $organization instanceof Organization) {
            $this->error('Organization not found.');

            return null;
        }

        if (! $organization->canTrackProcesses()) {
            $this->error('Organization cannot track processes.');

            return null;
        }

        return $organization;
    }

    private function radicadoFilter(): ?string
    {
        $filingOption = $this->option('radicado');

        if ($filingOption === null || $filingOption === '') {
            return null;
        }

        return (string) $filingOption;
    }

    private function finishWithoutProcesses(JudicialSyncRun $run, string $channel): int
    {
        $run->markNoProcesses();
        $this->info('No radicados to sync.');
        Log::channel($channel)->info('SyncJudicialProcessesForOrganizationCommand: No radicados to sync.', [
            'run_id' => $run->id,
            'organization_id' => $run->organization_id,
        ]);

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, mixed>  $processNumbers
     */
    private function dispatchBatch(
        JudicialSyncRun $run,
        string $organizationId,
        Collection $processNumbers,
        string $channel,
    ): int {
        $total = $processNumbers->count();
        $this->info("Found {$total} radicados to sync.");

        $jobs = $this->jobsFor($processNumbers);
        $runId = $run->id;

        try {
            $laravelBatch = Bus::batch($jobs)
                ->name('Sync Judicial Processes Batch (organization)')
                ->progress(function (Batch $batch) use ($runId): void {
                    $record = JudicialSyncRun::query()->find($runId);
                    if ($record !== null) {
                        $record->broadcastBatchProgress($batch);
                    }
                })
                ->finally(function (Batch $batch) use ($runId, $organizationId, $channel): void {
                    // Scoped digest: sibling organizations keep pending rows for their own run.
                    resolve(DispatchOrganizationSyncDigest::class)->handle($organizationId);

                    Log::channel($channel)->info('SyncJudicialProcessesForOrganizationCommand: Digest dispatch completed after batch', [
                        'run_id' => $runId,
                        'organization_id' => $organizationId,
                        'batch_id' => $batch->id,
                        'auto_digest' => config('judicial-sync.auto_digest_after_sync', true),
                    ]);

                    $record = JudicialSyncRun::query()->find($runId);
                    if ($record !== null) {
                        $record->completeBatch($batch);
                    }
                })
                ->onQueue('judicial-sync')
                ->dispatch();
        } catch (\Throwable $e) {
            $run->markDispatchFailed($e->getMessage());
            $this->error('Failed to dispatch batch: '.$e->getMessage());
            Log::channel($channel)->error('SyncJudicialProcessesForOrganizationCommand: Batch failing to dispatch', [
                'run_id' => $run->id,
                'organization_id' => $organizationId,
                'message' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        $run->markBatchQueued($laravelBatch->id, count($jobs), $laravelBatch);

        Log::channel($channel)->info('SyncJudicialProcessesForOrganizationCommand: Batch dispatched', [
            'run_id' => $run->id,
            'organization_id' => $organizationId,
            'jobs_count' => count($jobs),
            'laravel_batch_id' => $laravelBatch->id,
        ]);

        $this->info('Dispatched '.count($jobs).' sync jobs in a batch.');
        $this->info('Notifications for this organization will be consolidated upon batch completion.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, mixed>  $processNumbers
     * @return list<SyncProcessJob>
     */
    private function jobsFor(Collection $processNumbers): array
    {
        $jobs = [];

        foreach ($processNumbers as $processNumber) {
            $jobs[] = new SyncProcessJob((string) $processNumber);
        }

        return $jobs;
    }
}

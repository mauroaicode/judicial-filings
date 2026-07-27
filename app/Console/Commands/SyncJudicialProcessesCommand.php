<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Jobs\DispatchOrganizationDigestsJob;
use Src\Application\Shared\Jobs\SyncProcessJob;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;
use Src\Domain\Process\Models\Process;

class SyncJudicialProcessesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'judicial:sync-processes
                            {--radicado= : Optional radicado (process_number) to sync only that radicado}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync processes with Judicial Branch API and dispatch notification jobs';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');
        $filingOption = $this->option('radicado');
        $filingFilter = ($filingOption !== null && $filingOption !== '') ? $filingOption : null;

        $run = JudicialSyncRun::startRun($filingFilter, JudicialSyncDataSource::JudicialBranch);

        Log::channel($channel)->info('SyncJudicialProcessesCommand started', [
            'run_id' => $run->id,
            'data_source' => JudicialSyncDataSource::JudicialBranch->value,
            'radicado_filter' => $filingFilter,
        ]);

        $processNumbers = Process::query()
            ->forJudicialDailySync($filingFilter)
            ->pluck('processes.process_number');
        $total = $processNumbers->count();

        if ($total === 0) {
            $run->markNoProcesses();
            $this->info('No radicados to sync.');
            Log::channel($channel)->info('SyncJudicialProcessesCommand: No radicados to sync.', [
                'run_id' => $run->id,
            ]);

            return self::SUCCESS;
        }

        $this->info("Found {$total} radicados to sync.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $jobs = [];
        foreach ($processNumbers as $processNumber) {
            $jobs[] = new SyncProcessJob((string) $processNumber);
            $bar->advance();
        }

        $runId = $run->id;

        try {
            $laravelBatch = Bus::batch($jobs)
                ->name('Sync Judicial Processes Batch')
                ->finally(function (Batch $batch) use ($runId, $channel): void {
                    // Run digest dispatch synchronously: async dispatch() from this batch
                    // callback was not being processed reliably after large sync batches.
                    // Skipped when JUDICIAL_SYNC_AUTO_DIGEST=false so the admin can send
                    // one consolidated package after all sources (Rama + SAMAI + Excel) finish.
                    if (config('judicial-sync.auto_digest_after_sync', true)) {
                        DispatchOrganizationDigestsJob::dispatchSync();
                    }

                    Log::channel($channel)->info('SyncJudicialProcessesCommand: Digest dispatch completed after batch', [
                        'run_id' => $runId,
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

            $bar->finish();
            $this->newLine();

            $run->markBatchQueued($laravelBatch->id, count($jobs), $laravelBatch);

            Log::channel($channel)->info('SyncJudicialProcessesCommand: Batch dispatched', [
                'run_id' => $run->id,
                'jobs_count' => count($jobs),
                'laravel_batch_id' => $laravelBatch->id,
            ]);

            $this->info('Dispatched '.count($jobs).' sync jobs in a batch.');
            $this->info('Notifications will be consolidated and sent upon batch completion.');

        } catch (\Throwable $e) {
            $run->markDispatchFailed($e->getMessage());
            $this->error('Failed to dispatch batch: '.$e->getMessage());
            Log::channel($channel)->error('SyncJudicialProcessesCommand: Batch failing to dispatch', [
                'run_id' => $run->id,
                'message' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

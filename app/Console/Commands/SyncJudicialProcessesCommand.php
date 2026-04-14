<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Jobs\DispatchOrganizationDigestsJob;
use Src\Application\Shared\Jobs\SyncProcessJob;
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
        $radicado = $this->option('radicado');

        $processNumbers = $this->getProcessNumbersToSync($radicado);
        $total = $processNumbers->count();

        if ($total === 0) {
            $this->info('No radicados to sync.');

            return self::SUCCESS;
        }

        $this->info("Found {$total} radicados to sync.");

        Log::channel($channel)->info('SyncJudicialProcessesCommand started', [
            'radicados_count' => $total,
            'radicado_filter' => $radicado,
        ]);

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $jobs = [];
        foreach ($processNumbers as $processNumber) {
            $jobs[] = new SyncProcessJob((string) $processNumber);
            $bar->advance();
        }

        try {
            Bus::batch($jobs)
                ->name('Sync Judicial Processes Batch')
                ->finally(function () {
                    // This runs after all jobs in the batch are finished (success or failure)
                    DispatchOrganizationDigestsJob::dispatch();
                })
                ->onQueue('judicial-sync')
                ->dispatch();

            $bar->finish();
            $this->newLine();

            Log::channel($channel)->info('SyncJudicialProcessesCommand: Batch dispatched', [
                'jobs_count' => count($jobs),
            ]);

            $this->info('Dispatched '.count($jobs).' sync jobs in a batch.');
            $this->info('Notifications will be consolidated and sent upon batch completion.');

        } catch (\Throwable $e) {
            $this->error('Failed to dispatch batch: '.$e->getMessage());
            Log::channel($channel)->error('SyncJudicialProcessesCommand: Batch failing to dispatch', [
                'message' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Get distinct process_number (radicados) to sync.
     * Only radicados where at least one organization has the process active (is_active=1).
     * Avoids wasting API requests when no one is interested.
     *
     * @return Collection<int, string>
     */
    private function getProcessNumbersToSync(?string $radicadoFilter): Collection
    {
        $query = Process::query()
            ->join('organization_processes', 'processes.id', '=', 'organization_processes.process_id')
            ->where('organization_processes.is_active', true)
            ->distinct()
            ->select('processes.process_number');

        if ($radicadoFilter !== null && $radicadoFilter !== '') {
            $query->where('processes.process_number', $radicadoFilter);
        }

        return $query->pluck('processes.process_number');
    }
}

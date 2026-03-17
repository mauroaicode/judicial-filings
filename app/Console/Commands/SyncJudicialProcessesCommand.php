<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Jobs\SendOrganizationNotificationJob;
use Src\Application\Shared\Jobs\SyncProcessJob;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
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

    public function __construct(
        private readonly JudicialBranchConsultService $judicialService
    ) {
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

        $jobsDispatched = 0;
        foreach ($processNumbers as $processNumber) {
            SyncProcessJob::dispatch((string) $processNumber);
            $jobsDispatched++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        Log::channel($channel)->info('SyncJudicialProcessesCommand finished', [
            'jobs_dispatched' => $jobsDispatched,
        ]);

        $this->info("Dispatched {$jobsDispatched} sync jobs to the queue.");

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

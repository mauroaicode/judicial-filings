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

        if ($processNumbers->isEmpty()) {
            $this->info('No radicados to sync.');

            return self::SUCCESS;
        }

        Log::channel($channel)->info('SyncJudicialProcessesCommand started', [
            'radicados_count' => $processNumbers->count(),
            'radicado_filter' => $radicado,
        ]);

        foreach ($processNumbers as $processNumber) {
            $this->syncProcessesByRadicado((string) $processNumber);
        }

        $processIds = $this->getProcessIdsToSync($radicado);
        $jobsDispatched = 0;
        foreach ($processIds as $processId) {
            SyncProcessJob::dispatch($processId);
            $jobsDispatched++;
        }

        Log::channel($channel)->info('SyncJudicialProcessesCommand finished', [
            'jobs_dispatched' => $jobsDispatched,
        ]);

        $this->info("Dispatched {$jobsDispatched} sync jobs.");

        return self::SUCCESS;
    }

    /**
     * Get distinct process_number (radicados) to sync.
     *
     * @return Collection<int, string>
     */
    private function getProcessNumbersToSync(?string $radicadoFilter): Collection
    {
        $query = Process::query()->distinct()->select('process_number');

        if ($radicadoFilter !== null && $radicadoFilter !== '') {
            $query->where('process_number', $radicadoFilter);
        }

        return $query->pluck('process_number');
    }

    /**
     * Sync processes from API for one radicado: create new processes, link orgs, mark multiple instances, create notifications.
     *
     * @return void Number of new processes created (for logging)
     */
    private function syncProcessesByRadicado(string $processNumber): void
    {
        $result = $this->judicialService->fetchProcesses($processNumber);
        if (! $result->isSuccessful || ! is_array($result->data)) {
            return;
        }

        $apiProcesos = $result->data;
        $created = 0;

        foreach ($apiProcesos as $apiProceso) {
            $apiProcessId = (int) ($apiProceso['idProceso'] ?? 0);
            if ($apiProcessId === 0) {
                continue;
            }

            $process = Process::query()->where('process_id', $apiProcessId)->first();

            if ($process === null) {
                $process = $this->createProcessFromApi($apiProceso);
                $this->linkOrganizationsToNewProcess($process);
                $created++;
            }
        }

        $processesForRadicado = Process::query()->where('process_number', $processNumber)->get();

        if ($processesForRadicado->count() > 1) {
            Process::query()->where('process_number', $processNumber)->update(['has_multiple_instances' => true]);
            if ($created > 0) {
                $this->createMultipleInstancesNotifications($processesForRadicado);
            }
        }

    }

    /**
     * @param  array<string, mixed>  $apiProceso
     */
    private function createProcessFromApi(array $apiProceso): Process
    {
        $apiProcessId = (int) ($apiProceso['idProceso'] ?? 0);
        $detail = $this->judicialService->fetchDetailProcess($apiProcessId);

        if (! $detail->isSuccessful || ! is_array($detail->data)) {
            $court = (string) ($apiProceso['despacho'] ?? 'N/A');
            $department = (string) ($apiProceso['departamento'] ?? 'N/A');
            $processDate = $this->parseDate($apiProceso['fechaProceso'] ?? null);
            $lastActivity = $this->parseDate($apiProceso['fechaUltimaActuacion'] ?? null);

            return Process::query()->create([
                'process_id' => $apiProcessId,
                'process_number' => (string) ($apiProceso['llaveProceso'] ?? ''),
                'court' => $court,
                'department' => $department,
                'process_type' => 'N/A',
                'process_class' => 'N/A',
                'subclass_process' => null,
                'litigants' => $apiProceso['sujetosProcesales'] ?? null,
                'process_date' => $processDate ?? now()->format('Y-m-d'),
                'last_activity_date' => $lastActivity,
                'location' => null,
                'filing_content' => null,
                'is_private' => (bool) ($apiProceso['esPrivado'] ?? false),
                'has_multiple_instances' => false,
                'last_api_update' => now(),
            ]);
        }

        $data = $detail->data;

        return Process::query()->create([
            'process_id' => $apiProcessId,
            'process_number' => (string) ($data['llaveProceso'] ?? $apiProceso['llaveProceso'] ?? ''),
            'court' => (string) ($data['despacho'] ?? $apiProceso['despacho'] ?? 'N/A'),
            'department' => (string) ($data['departamento'] ?? $apiProceso['departamento'] ?? 'N/A'),
            'process_type' => (string) ($data['tipoProceso'] ?? 'N/A'),
            'process_class' => (string) ($data['claseProceso'] ?? 'N/A'),
            'subclass_process' => isset($data['subclaseProceso']) ? (string) $data['subclaseProceso'] : null,
            'litigants' => $data['sujetosProcesales'] ?? $apiProceso['sujetosProcesales'] ?? null,
            'process_date' => $this->parseDate($data['fechaProceso'] ?? $apiProceso['fechaProceso'] ?? null) ?? now()->format('Y-m-d'),
            'last_activity_date' => $this->parseDate($data['fechaUltimaActuacion'] ?? $apiProceso['fechaUltimaActuacion'] ?? null),
            'location' => isset($data['ubicacion']) ? (string) $data['ubicacion'] : null,
            'filing_content' => isset($data['contenidoRadicacion']) ? (string) $data['contenidoRadicacion'] : null,
            'is_private' => (bool) ($data['esPrivado'] ?? $apiProceso['esPrivado'] ?? false),
            'has_multiple_instances' => false,
            'last_api_update' => now(),
        ]);
    }

    private function parseDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function linkOrganizationsToNewProcess(Process $process): void
    {
        $rows = OrganizationProcess::query()
            ->join('processes', 'organization_processes.process_id', '=', 'processes.id')
            ->where('processes.process_number', $process->process_number)
            ->select('organization_processes.organization_id', 'organization_processes.is_active')
            ->get();

        $orgActive = [];
        foreach ($rows as $row) {
            $id = $row->organization_id;
            if (! isset($orgActive[$id])) {
                $orgActive[$id] = false;
            }
            if ($row->is_active) {
                $orgActive[$id] = true;
            }
        }

        foreach ($orgActive as $organizationId => $isActive) {
            OrganizationProcess::query()->firstOrCreate(
                [
                    'organization_id' => $organizationId,
                    'process_id' => $process->id,
                ],
                [
                    'interest_date' => now()->format('Y-m-d'),
                    'is_active' => $isActive,
                ]
            );
        }
    }

    /**
     * @param  Collection<int, Process>  $processes
     */
    private function createMultipleInstancesNotifications(Collection $processes): void
    {
        foreach ($processes as $process) {
            // Solo organizaciones con seguimiento activo: se notifica y se guarda historial.
            $organizations = $process->organizations()->wherePivot('is_active', true)->get();

            foreach ($organizations as $organization) {
                $notification = OrganizationNotification::query()->firstOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'notifiable_id' => $process->id,
                        'notifiable_type' => $process->getMorphClass(),
                    ],
                    [
                        'notification_type' => 'multiple_instances',
                        'is_viewed' => false,
                        'is_notified' => false,
                    ]
                );

                dispatch(SendOrganizationNotificationJob::fromNotification($notification));
            }
        }
    }

    /**
     * Get process IDs to sync (for dispatching SyncProcessJob).
     *
     * @return Collection<int, string>
     */
    private function getProcessIdsToSync(?string $radicadoFilter): Collection
    {
        $query = Process::query()->select('id');

        if ($radicadoFilter !== null && $radicadoFilter !== '') {
            $query->where('process_number', $radicadoFilter);
        }

        return $query->pluck('id');
    }
}

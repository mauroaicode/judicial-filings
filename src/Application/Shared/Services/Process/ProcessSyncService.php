<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Jobs\SendOrganizationNotificationJob;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Application\Shared\Traits\MapsJudicialActuacionTrait;
use Src\Application\Shared\Traits\MapsJudicialSujetoTrait;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessSubject;

class ProcessSyncService
{
    use MapsJudicialActuacionTrait;
    use MapsJudicialSujetoTrait;

    public function __construct(
        private readonly JudicialBranchConsultService $judicialService,
        private readonly ProcessActionAlertNotificationService $processActionAlertNotificationService
    ) {}

    public function handle(Process $process, bool $notify = true): void
    {
        $logChannel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        $apiProcessId = $process->process_id;

        $this->judicialService->withSeed($process->process_number);

        $onlyFirstPage = $process->actions()->exists();
        $actionsResult = $this->judicialService->fetchActionByProcess($apiProcessId, $onlyFirstPage);

        if (! $actionsResult->isSuccessful) {
            Log::channel($logChannel)->error('ProcessSyncService: failed to fetch actuaciones', [
                'process_id' => $process->id,
            ]);
            throw new \RuntimeException(__('process.sync_failed_actuaciones'));
        }

        $this->syncActuaciones($process, $actionsResult->data, $notify);

        // Optimización: solo consultar sujetos si el proceso aún no tiene sujetos registrados.
        if (! $process->subjects()->exists()) {
            $subjectsResult = $this->judicialService->fetchSubjectsByProcess($apiProcessId);
            if (! $subjectsResult->isSuccessful) {
                Log::channel($logChannel)->error('ProcessSyncService: failed to fetch sujetos', [
                    'process_id' => $process->id,
                ]);
                throw new \RuntimeException(__('process.sync_failed_sujetos'));
            }

            $this->syncSujetos($process, $subjectsResult->data);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $apiActuaciones
     */
    private function syncActuaciones(Process $process, array $apiActuaciones, bool $notify = true): void
    {
        $logChannel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        $hasNewActions = false;
        $maxActionDate = null;

        foreach ($apiActuaciones as $apiActuacion) {
            $idReg = (int) ($apiActuacion['idRegActuacion'] ?? 0);
            if ($idReg === 0) {
                continue;
            }

            if (ProcessAction::query()->existsByActionRegistrationId($process->id, $idReg)) {
                continue;
            }

            $attributes = $this->mapApiActuacionToAttributes($apiActuacion);
            $attributes['process_id'] = $process->id;

            $action = ProcessAction::query()->create($attributes);
            $hasNewActions = true;

            $actionDate = Date::parse($attributes['action_date']);
            if (! $maxActionDate instanceof Carbon || $actionDate->greaterThan($maxActionDate)) {
                $maxActionDate = $actionDate;
            }

            Log::channel($logChannel)->info('ProcessSyncService: New action saved', [
                'action_id' => $action->id,
                'process_id' => $process->id,
                'reg_id' => $idReg,
            ]);

            if ($notify) {
                Log::channel($logChannel)->info('ProcessSyncService: Triggering notifications for action', [
                    'action_id' => $action->id,
                ]);
                $this->processActionAlertNotificationService->handle($action, $process);
            }
        }

        $dbMaxDate = $process->actions()->max('action_date');

        $updateData = [
            'last_api_update' => now(),
        ];

        if ($dbMaxDate) {
            $dbMaxDateStr = Date::parse($dbMaxDate)->format('Y-m-d');
            $currentDateStr = $process->last_activity_date ? $process->last_activity_date->format('Y-m-d') : null;

            if ($currentDateStr === null || $dbMaxDateStr > $currentDateStr) {
                $updateData['last_activity_date'] = $dbMaxDateStr;
            }
        }

        $process->update($updateData);

        if ($hasNewActions) {
            OrganizationProcess::query()
                ->where('process_id', $process->id)
                ->update(['inactivity_alert_level' => null]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $apiSujetos
     */
    private function syncSujetos(Process $process, array $apiSujetos): void
    {
        foreach ($apiSujetos as $apiSujeto) {
            $idReg = (int) ($apiSujeto['idRegSujeto'] ?? 0);
            if ($idReg === 0) {
                continue;
            }

            $attributes = $this->mapApiSujetoToAttributes($apiSujeto);
            $subject = ProcessSubject::query()->firstOrCreate(
                ['subject_registration_id' => $idReg],
                $attributes
            );

            $process->subjects()->syncWithoutDetaching([$subject->id]);
        }
    }

    /**
     * Sync actuaciones and sujetos for all process instances of a radicado with one API call.
     * Only processes that are active in at least one organization are synced.
     */
    public function syncByProcessNumber(string $processNumber, bool $notify = true): void
    {
        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        Log::channel($channel)->info('ProcessSyncService: Starting sync for radicado', [
            'process_number' => $processNumber,
        ]);

        // 1. Discovery: Search for new instances in the API (moved from Command)
        $this->discoverNewProcesses($processNumber);

        // 2. Fetch processes to sync (all active instances)
        $processes = Process::query()
            ->where('process_number', $processNumber)
            ->whereHas('organizations', fn (Builder $q) => $q->where('organization_processes.is_active', true))
            ->get();

        if ($processes->isEmpty()) {
            Log::channel($channel)->info('ProcessSyncService: no active processes for radicado', [
                'process_number' => $processNumber,
            ]);

            return;
        }

        Log::channel($channel)->info('ProcessSyncService: found active instances to sync', [
            'process_number' => $processNumber,
            'instances_count' => $processes->count(),
        ]);

        $this->judicialService->withSeed($processNumber);

        foreach ($processes as $process) {
            $apiProcessId = (int) $process->process_id;

            // Optimización: si ya tenemos actuaciones, solo consultamos la página 1 para detectar novedades.
            $onlyFirstPage = $process->actions()->exists();

            $actionsResult = $this->judicialService->fetchActionByProcess($apiProcessId, $onlyFirstPage);
            if (! $actionsResult->isSuccessful) {
                Log::channel($channel)->error('ProcessSyncService: failed to fetch actuaciones', [
                    'process_number' => $processNumber,
                    'process_id' => $process->id,
                ]);

                continue;
            }

            $this->syncActuaciones($process, $actionsResult->data, $notify);

            // Optimización: solo consultar sujetos si el proceso aún no tiene sujetos registrados.
            if (! $process->subjects()->exists()) {
                $subjectsResult = $this->judicialService->fetchSubjectsByProcess($apiProcessId);
                if (! $subjectsResult->isSuccessful) {
                    Log::channel($channel)->error('ProcessSyncService: failed to fetch sujetos', [
                        'process_number' => $processNumber,
                        'process_id' => $process->id,
                    ]);

                    continue;
                }

                $this->syncSujetos($process, $subjectsResult->data);
            }

            Log::channel($channel)->info('ProcessSyncService: instance sync completed', [
                'process_number' => $processNumber,
                'process_id' => $process->id,
            ]);
        }

        Log::channel($channel)->info('ProcessSyncService: finished sync for radicado', [
            'process_number' => $processNumber,
        ]);
    }

    /**
     * Logic from Command: Sync processes list from API, create new processes if found, and link organizations.
     */
    private function discoverNewProcesses(string $processNumber): void
    {
        $this->judicialService->withSeed($processNumber);
        $result = $this->judicialService->fetchProcesses($processNumber);

        if (! $result->isSuccessful) {
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
            } else {
                // If it already exists, update metadata from the process list info
                $lastActivity = $this->parseDate($apiProceso['fechaUltimaActuacion'] ?? null);
                $updateData = ['last_api_update' => now()];

                if ($lastActivity && ($process->last_activity_date === null || $lastActivity > $process->last_activity_date->format('Y-m-d'))) {
                    $updateData['last_activity_date'] = $lastActivity;
                }

                $process->update($updateData);
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

        if (! $detail->isSuccessful) {
            $court = (string) ($apiProceso['despacho'] ?? 'N/A');
            $department = (string) ($apiProceso['departamento'] ?? 'N/A');
            $processDate = $this->parseDate($apiProceso['fechaProceso'] ?? null);
            $lastActivity = $this->parseDate($apiProceso['fechaUltimaActuacion'] ?? null);

            return Process::query()->create([
                'process_id' => $apiProcessId,
                'process_number' => (string) ($apiProceso['llaveProceso'] ?? ''),
                'court' => $court,
                'speaker' => (string) ($apiProceso['ponente'] ?? null),
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
            'speaker' => (string) ($data['ponente'] ?? $apiProceso['ponente'] ?? null),
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
            return Date::parse($date)->format('Y-m-d');
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
     * @param  \Illuminate\Support\Collection<int, Process>  $processes
     */
    private function createMultipleInstancesNotifications(\Illuminate\Support\Collection $processes): void
    {
        foreach ($processes as $process) {
            $organizations = $process->organizations()->wherePivot('is_active', true)->get();

            foreach ($organizations as $organization) {
                $notification = OrganizationNotification::query()->firstOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'notifiable_id' => $process->id,
                        'notifiable_type' => $process->getMorphClass(),
                        'notification_type' => 'multiple_instances',
                    ],
                    [
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'is_viewed' => false,
                        'is_notified' => false,
                    ]
                );

                dispatch(SendOrganizationNotificationJob::fromNotification($notification));
            }
        }
    }
}

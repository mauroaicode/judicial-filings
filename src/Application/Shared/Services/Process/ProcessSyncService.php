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
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessDataSource;
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

        $process->loadMissing('processDataSource');
        if ($process->is_manual_sync || $process->process_id === null || $process->processDataSource?->slug !== ProcessDataSourceSlug::JudicialBranch->value) {
            Log::channel($logChannel)->info('ProcessSyncService::handle skipped: not a judicial branch candidate', [
                'process_uuid' => $process->id,
            ]);

            return;
        }

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
     * @param  Carbon|null  $notifyFromDate  When set, only notify for actions on or after this date.
     *                                       Allows storing full history while suppressing notifications
     *                                       for historical actuaciones in newly discovered instances.
     */
    private function syncActuaciones(Process $process, array $apiActuaciones, bool $notify = true, ?Carbon $notifyFromDate = null): void
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
                // Apply date guard: for newly discovered instances, skip notifications for
                // historical actuaciones that predate the radicado's known activity window.
                // The action is still persisted above for full historical traceability.
                if ($notifyFromDate instanceof \Illuminate\Support\Carbon && $actionDate->lt($notifyFromDate)) {
                    Log::channel($logChannel)->info('ProcessSyncService: Skipping notification for historical action (below notify-from date)', [
                        'action_id' => $action->id,
                        'action_date' => $actionDate->format('Y-m-d'),
                        'notify_from_date' => $notifyFromDate->format('Y-m-d'),
                    ]);

                    continue;
                }

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

        // 2. Fetch processes to sync (all active instances from Rama only)
        $processes = Process::query()
            ->where('process_number', $processNumber)
            ->where('is_manual_sync', false)
            ->whereNotNull('process_id')
            ->whereHas('organizations', fn (Builder $q) => $q->where('organization_processes.is_active', true))
            ->whereHas('processDataSource', fn (Builder $q) => $q->where('slug', ProcessDataSourceSlug::JudicialBranch->value))
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

        $thresholdDays = (int) config('judicial-sync.inactive_skip_threshold_days', 2);

        // Compute notification cutoff for newly discovered instances:
        // any instance without existing actions will only trigger notifications
        // for actuaciones on or after this date, preventing historical floods.
        $radicadoNotifyFromDate = $this->resolveRadicadoNotifyFromDate($processNumber);

        // Cache for the first-page actuaciones result.
        // The Rama Judicial publishes the same actuaciones in all duplicate instances
        // of a radicado. For instances that already have actions (onlyFirstPage=true),
        // the first API call result is reused by subsequent instances, saving N-1
        // proxy requests per radicado with duplicate folders.
        /** @var array<int, array<string, mixed>>|null */
        $cachedFirstPageActuaciones = null;

        foreach ($processes as $process) {
            $apiProcessId = (int) $process->process_id;

            // Optimización: si el proceso lleva más de N días sin actividad registrada,
            // se omite fetchActionByProcess para ahorrar una petición al proxy.
            // Los procesos activos en las últimas 48h siempre se consultan, garantizando
            // que el cron de las 3:30pm capture actuaciones ocurridas tras el cron de las 9am.
            if ($thresholdDays > 0 && $process->last_activity_date !== null
                && $process->last_activity_date->lt(now()->subDays($thresholdDays)->startOfDay())) {
                Log::channel($channel)->info('ProcessSyncService: skipping actuaciones fetch (inactive process)', [
                    'process_number' => $processNumber,
                    'process_id' => $process->id,
                    'last_activity_date' => $process->last_activity_date->format('Y-m-d'),
                    'threshold_days' => $thresholdDays,
                ]);

                continue;
            }

            // Optimización: si ya tenemos actuaciones, solo consultamos la página 1 para detectar novedades.
            $onlyFirstPage = $process->actions()->exists();

            // For instances that already have actions, the existsByActionRegistrationId check
            // naturally prevents re-storing (and thus re-notifying) old actuaciones.
            // For brand-new instances (no actions yet), apply the date cutoff so that
            // fetching the full historical record does not flood clients with old notifications.
            $notifyFromDate = $onlyFirstPage ? null : $radicadoNotifyFromDate;

            // Reuse cached first-page result for duplicate instances (onlyFirstPage=true only).
            // A full-history result (onlyFirstPage=false) is never cached because it differs
            // in length from a first-page result and each new instance must store all its records.
            if ($onlyFirstPage && $cachedFirstPageActuaciones !== null) {
                Log::channel($channel)->info('ProcessSyncService: reusing cached first-page actuaciones for duplicate instance', [
                    'process_number' => $processNumber,
                    'process_id' => $process->id,
                ]);
                $apiActuaciones = $cachedFirstPageActuaciones;
            } else {
                $actionsResult = $this->judicialService->fetchActionByProcess($apiProcessId, $onlyFirstPage);
                if (! $actionsResult->isSuccessful) {
                    Log::channel($channel)->error('ProcessSyncService: failed to fetch actuaciones', [
                        'process_number' => $processNumber,
                        'process_id' => $process->id,
                    ]);

                    continue;
                }

                $apiActuaciones = $actionsResult->data;

                // Cache only first-page results for reuse by sibling instances.
                if ($onlyFirstPage) {
                    $cachedFirstPageActuaciones = $apiActuaciones;
                }
            }

            $this->syncActuaciones($process, $apiActuaciones, $notify, $notifyFromDate);

            // Sujetos: only fetch from API when this instance has no subjects AND no sibling
            // instance has provided them yet. After the loop, propagateSubjectsAcrossInstances
            // will link any fetched subjects to all instances that still lack them —
            // avoiding repeated API calls for duplicate instances that return empty subject data.
            if (! $process->subjects()->exists()) {
                $subjectsResult = $this->judicialService->fetchSubjectsByProcess($apiProcessId);
                if (! $subjectsResult->isSuccessful) {
                    Log::channel($channel)->error('ProcessSyncService: failed to fetch sujetos', [
                        'process_number' => $processNumber,
                        'process_id' => $process->id,
                    ]);
                } else {
                    $this->syncSujetos($process, $subjectsResult->data);
                }
            }

            Log::channel($channel)->info('ProcessSyncService: instance sync completed', [
                'process_number' => $processNumber,
                'process_id' => $process->id,
            ]);
        }

        // Propagate subjects from the instance that has real data to all sibling instances
        // that still lack them. The Rama Judicial only populates Demandante/Demandado in
        // one of the duplicate folders; this ensures all instances end up with those subjects.
        $this->propagateSubjectsAcrossInstances($processes);

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
                ...$this->judicialBranchProcessIdentifiers(),
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
            ...$this->judicialBranchProcessIdentifiers(),
        ]);
    }

    /**
     * @return array{is_manual_sync: bool, process_data_source_id: string}
     */
    private function judicialBranchProcessIdentifiers(): array
    {
        return [
            'is_manual_sync' => false,
            'process_data_source_id' => ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::JudicialBranch),
        ];
    }

    /**
     * After syncing all instances of a radicado, ensure every instance has the same
     * subjects linked. The Rama Judicial only populates Demandante/Demandado in one
     * of the duplicate folders; the rest come back empty or with "---".
     *
     * This runs a single DB query to collect all unique subject IDs across all instances
     * and then links them to any instance that is still missing them — no extra API calls.
     *
     * @param  \Illuminate\Support\Collection<int, Process>  $processes
     */
    private function propagateSubjectsAcrossInstances(\Illuminate\Support\Collection $processes): void
    {
        $logChannel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        // Collect all unique subject UUIDs from every instance in this radicado.
        $allSubjectIds = [];
        foreach ($processes as $process) {
            foreach ($process->subjects()->pluck('process_subjects.id') as $id) {
                $allSubjectIds[(string) $id] = true;
            }
        }

        if ($allSubjectIds === []) {
            return;
        }

        $subjectIds = array_keys($allSubjectIds);

        foreach ($processes as $process) {
            $existing = $process->subjects()->pluck('process_subjects.id')->map(fn ($v): string => (string) $v)->all();
            $missing = array_diff($subjectIds, $existing);

            if ($missing !== []) {
                $process->subjects()->syncWithoutDetaching($missing);

                Log::channel($logChannel)->info('ProcessSyncService: propagated subjects to sibling instance', [
                    'process_id' => $process->id,
                    'subjects_added' => count($missing),
                ]);
            }
        }
    }

    /**
     * Determine the earliest date from which notifications should be triggered
     * when a new process instance (with no existing actions) is synced during
     * the daily cron.
     *
     * Priority:
     * 1. Max last_activity_date from sibling instances that already have synced
     *    actuaciones — this is the "known state" of the radicado.
     * 2. Fallback: now() minus the configured window (new_instance_notify_days).
     *
     * Historical actuaciones are always stored; this date only gates notifications.
     */
    private function resolveRadicadoNotifyFromDate(string $processNumber): Carbon
    {
        $maxDate = Process::query()
            ->where('process_number', $processNumber)
            ->whereNotNull('last_activity_date')
            ->whereHas('actions')
            ->max('last_activity_date');

        if ($maxDate) {
            return Date::parse($maxDate)->startOfDay();
        }

        $fallbackDays = (int) config('judicial-sync.new_instance_notify_days', 7);

        if ($fallbackDays > 0) {
            return now()->subDays($fallbackDays)->startOfDay();
        }

        // fallback_days = 0 means no fixed-window fallback; use today as boundary
        // so at least actuaciones from today are always notified.
        return today();
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

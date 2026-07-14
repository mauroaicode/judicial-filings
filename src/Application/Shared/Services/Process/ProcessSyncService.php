<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Helpers\ProcessPhantomInstanceHelper;
use Src\Application\Shared\Helpers\ProcessSubjectIdentityHelper;
use Src\Application\Shared\Jobs\SendOrganizationNotificationJob;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Application\Shared\Services\Notification\NotificationDigestService;
use Src\Application\Shared\Traits\MapsJudicialActuacionTrait;
use Src\Application\Shared\Traits\MapsJudicialSujetoTrait;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
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
        private readonly ProcessActionAlertNotificationService $processActionAlertNotificationService,
        private readonly NotificationDigestService $notificationDigestService,
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

        $siblings = Process::query()->where('process_number', $process->process_number)->get();
        if (ProcessPhantomInstanceHelper::isLikelyPhantomDuplicate($process, $siblings)) {
            Log::channel($logChannel)->info('ProcessSyncService::handle skipped actuaciones for phantom duplicate instance', [
                'process_uuid' => $process->id,
                'process_number' => $process->process_number,
            ]);

            return;
        }

        $onlyFirstPage = $process->actions()->exists();
        $actionsResult = $this->judicialService->fetchActionByProcess($apiProcessId, $onlyFirstPage);

        if (! $actionsResult->isSuccessful) {
            Log::channel($logChannel)->error('ProcessSyncService: failed to fetch actuaciones', [
                'process_id' => $process->id,
            ]);
            throw new \RuntimeException(__('process.sync_failed_actuaciones'));
        }

        $this->syncActuaciones($process, $actionsResult->data, $notify);
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
     * Sync actuaciones for a radicado during manual registration or bulk import.
     *
     * - Saves full history for brand-new instances; first page only for existing ones.
     * - Reuses the same instance-sync loop as the daily cron.
     * - Queues digest notifications only for the registering organization.
     * - Only notifies actuaciones within the registration alert window (today/tomorrow).
     */
    public function syncForRegistration(string $processNumber, string $organizationId, bool $dispatchDigest = true): void
    {
        $processes = Process::query()
            ->where('process_number', $processNumber)
            ->where('is_manual_sync', false)
            ->whereNotNull('process_id')
            ->whereHas('processDataSource', fn (Builder $q) => $q->where('slug', ProcessDataSourceSlug::JudicialBranch->value))
            ->get();

        if ($processes->isEmpty()) {
            return;
        }

        $this->judicialService->withSeed($processNumber);

        $lock = Cache::lock('judicial-sync:radicado:'.$processNumber, 300);

        $lock->block(120, function () use ($processNumber, $processes, $organizationId, $dispatchDigest): void {
            $this->syncInstancesForRadicado(
                processNumber: $processNumber,
                processes: $processes,
                notify: true,
                scopedOrganizationId: $organizationId,
                registrationMode: true,
                skipInactiveThreshold: true,
            );

            foreach ($processes as $process) {
                if (! $process->organizations()->where('organizations.id', $organizationId)->exists()) {
                    continue;
                }

                $this->notifyRecentExistingActionsForOrganization($process, $organizationId);
            }

            if ($dispatchDigest) {
                $this->dispatchRegistrationDigestIfPending($organizationId);
            }
        });
    }

    /**
     * Build the consolidated digest after registration/import when recent actuaciones were queued.
     */
    public function dispatchRegistrationDigestIfPending(string $organizationId, ?array $limitToProcessNumbers = null): void
    {
        $organization = Organization::query()->find($organizationId);

        if ($organization === null) {
            return;
        }

        $morphClass = (new ProcessAction)->getMorphClass();
        $hasPending = $organization->notifications()
            ->forActiveOrganizationProcesses($organizationId)
            ->where('notifiable_type', $morphClass)
            ->where(function ($q): void {
                $q->where('is_email_notified', false)
                    ->orWhere(function ($q2): void {
                        $q2->whereNull('notification_digest_id')
                            ->where('notification_type', 'actuacion_registro');
                    });
            });

        if ($limitToProcessNumbers !== null && $limitToProcessNumbers !== []) {
            $hasPending->forProcessNumbers($limitToProcessNumbers);
        }

        if (! $hasPending->exists()) {
            return;
        }

        Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
            ->info('ProcessSyncService: Dispatching registration digest', [
                'organization_id' => $organizationId,
                'limit_to_process_numbers' => $limitToProcessNumbers,
            ]);

        $this->notificationDigestService->sendDigest($organization, $limitToProcessNumbers);
    }

    /**
     * @param  array<int, array<string, mixed>>  $apiActuaciones
     * @param  Carbon|null  $notifyFromDate  When set, only notify for actions on or after this date.
     *                                       Allows storing full history while suppressing notifications
     *                                       for historical actuaciones in newly discovered instances.
     */
    private function syncActuaciones(
        Process $process,
        array $apiActuaciones,
        bool $notify = true,
        ?Carbon $notifyFromDate = null,
        ?string $scopedOrganizationId = null,
        bool $registrationMode = false,
    ): void {
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

            if ((bool) config('judicial-sync.dedupe_actions_by_content', true)
                && ProcessAction::query()->existsDuplicateForRadicado($process->process_number, $attributes)) {
                Log::channel($logChannel)->info('ProcessSyncService: skipping cross-instance duplicate action (content match)', [
                    'process_id' => $process->id,
                    'process_number' => $process->process_number,
                    'reg_id' => $idReg,
                    'action' => $attributes['action'],
                    'action_date' => $attributes['action_date'],
                ]);

                continue;
            }

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
                $this->maybeNotifyForAction(
                    $action,
                    $process,
                    $actionDate,
                    $notifyFromDate,
                    $scopedOrganizationId,
                    $registrationMode,
                );
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

            $subject = ProcessSubjectIdentityHelper::findCanonicalForRadicado(
                $process->process_number,
                $attributes['subject_type'],
                $attributes['name_or_business_name'],
                $attributes['identification'],
            );

            if (! $subject instanceof ProcessSubject) {
                $subject = ProcessSubject::query()->firstOrCreate(
                    ['subject_registration_id' => $idReg],
                    $attributes
                );
            }

            $process->subjects()->syncWithoutDetaching([$subject->id]);
        }
    }

    /**
     * Sync actuaciones and sujetos for all process instances of a radicado with one API call.
     * Only processes that are active in at least one organization are synced.
     */
    public function syncByProcessNumber(string $processNumber, bool $notify = true, bool $skipInactiveThreshold = false): void
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

        $lock = Cache::lock('judicial-sync:radicado:'.$processNumber, 300);

        $lock->block(120, function () use ($processNumber, $processes, $notify, $skipInactiveThreshold, $channel): void {
            $this->syncInstancesForRadicado(
                processNumber: $processNumber,
                processes: $processes,
                notify: $notify,
                skipInactiveThreshold: $skipInactiveThreshold,
            );

            Log::channel($channel)->info('ProcessSyncService: finished sync for radicado', [
                'process_number' => $processNumber,
            ]);
        });
    }

    /**
     * Shared sync loop for cron and registration flows.
     *
     * @param  \Illuminate\Support\Collection<int, Process>  $processes
     */
    private function syncInstancesForRadicado(
        string $processNumber,
        \Illuminate\Support\Collection $processes,
        bool $notify,
        ?string $scopedOrganizationId = null,
        bool $registrationMode = false,
        bool $skipInactiveThreshold = false,
    ): void {
        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');
        $thresholdDays = (int) config('judicial-sync.inactive_skip_threshold_days', 2);
        $radicadoNotifyFromDate = $registrationMode ? null : $this->resolveRadicadoNotifyFromDate($processNumber);

        foreach ($processes as $process) {
            $apiProcessId = (int) $process->process_id;

            if (ProcessPhantomInstanceHelper::isLikelyPhantomDuplicate($process, $processes)) {
                Log::channel($channel)->info('ProcessSyncService: skipping phantom duplicate instance actuaciones sync', [
                    'process_number' => $processNumber,
                    'process_id' => $process->id,
                ]);

                continue;
            }

            if (! $skipInactiveThreshold
                && ! $this->hasPendingActuacionesSync($process)
                && $thresholdDays > 0
                && $process->last_activity_date !== null
                && $process->last_activity_date->lt(now()->subDays($thresholdDays)->startOfDay())) {
                Log::channel($channel)->info('ProcessSyncService: skipping actuaciones fetch (inactive process)', [
                    'process_number' => $processNumber,
                    'process_id' => $process->id,
                    'last_activity_date' => $process->last_activity_date->format('Y-m-d'),
                    'threshold_days' => $thresholdDays,
                ]);

                continue;
            }

            $onlyFirstPage = $process->actions()->exists();
            $notifyFromDate = ($onlyFirstPage || $registrationMode) ? null : $radicadoNotifyFromDate;

            $actionsResult = $this->judicialService->fetchActionByProcess($apiProcessId, $onlyFirstPage);
            if (! $actionsResult->isSuccessful) {
                Log::channel($channel)->error('ProcessSyncService: failed to fetch actuaciones', [
                    'process_number' => $processNumber,
                    'process_id' => $process->id,
                ]);

                continue;
            }

            $apiActuaciones = $actionsResult->data;

            $this->syncActuaciones(
                $process,
                $apiActuaciones,
                $notify,
                $notifyFromDate,
                $scopedOrganizationId,
                $registrationMode,
            );

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

        $this->propagateSubjectsAcrossInstances($processes);
    }

    private function maybeNotifyForAction(
        ProcessAction $action,
        Process $process,
        Carbon $actionDate,
        ?Carbon $notifyFromDate,
        ?string $scopedOrganizationId,
        bool $registrationMode,
    ): void {
        $logChannel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        if ($registrationMode && $scopedOrganizationId !== null) {
            if (! $this->isRecentForRegistrationAlert($action)) {
                return;
            }

            Log::channel($logChannel)->info('ProcessSyncService: Registration alert for recent action', [
                'action_id' => $action->id,
                'organization_id' => $scopedOrganizationId,
            ]);
            $this->processActionAlertNotificationService->handleForOrganizationRegistration(
                $action,
                $process,
                $scopedOrganizationId,
            );

            return;
        }

        if ($notifyFromDate instanceof Carbon && $actionDate->lt($notifyFromDate)) {
            Log::channel($logChannel)->info('ProcessSyncService: Skipping notification for historical action (below notify-from date)', [
                'action_id' => $action->id,
                'action_date' => $actionDate->format('Y-m-d'),
                'notify_from_date' => $notifyFromDate->format('Y-m-d'),
            ]);

            return;
        }

        Log::channel($logChannel)->info('ProcessSyncService: Triggering notifications for action', [
            'action_id' => $action->id,
        ]);
        $this->processActionAlertNotificationService->handle($action, $process);
    }

    private function notifyRecentExistingActionsForOrganization(Process $process, string $organizationId): void
    {
        $actions = ProcessAction::query()
            ->whereProcess($process->id)
            ->get();

        foreach ($actions as $action) {
            if (! $this->isRecentForRegistrationAlert($action)) {
                continue;
            }

            $this->processActionAlertNotificationService->handleForOrganizationRegistration(
                $action,
                $process,
                $organizationId,
            );
        }
    }

    private function isRecentForRegistrationAlert(ProcessAction $action): bool
    {
        $from = today()->startOfDay();
        $forwardDays = (int) config('judicial-sync.registration_alert_days_forward', 1);
        $to = today()->addDays($forwardDays)->endOfDay();

        foreach ([$action->action_date, $action->registration_date] as $date) {
            if ($date->betweenIncluded($from, $to)) {
                return true;
            }
        }

        return false;
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

                // Update court/speaker/department in case the process moved to a different court
                $apiCourt = trim((string) ($apiProceso['despacho'] ?? ''));
                $apiSpeaker = trim((string) ($apiProceso['ponente'] ?? ''));
                $apiDepartment = trim((string) ($apiProceso['departamento'] ?? ''));

                if ($apiCourt !== '' && $apiCourt !== $process->court) {
                    $updateData['court'] = $apiCourt;
                }

                if ($apiSpeaker !== '' && $apiSpeaker !== $process->speaker) {
                    $updateData['speaker'] = $apiSpeaker;
                }

                if ($apiDepartment !== '' && $apiDepartment !== $process->department) {
                    $updateData['department'] = $apiDepartment;
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

        /** @var array<string, ProcessSubject> $canonicalByIdentity */
        $canonicalByIdentity = [];

        foreach ($processes as $process) {
            foreach ($process->subjects as $subject) {
                $identityKey = ProcessSubjectIdentityHelper::key($subject);

                if (! isset($canonicalByIdentity[$identityKey])) {
                    $canonicalByIdentity[$identityKey] = $subject;

                    continue;
                }

                $canonicalByIdentity[$identityKey] = ProcessSubjectIdentityHelper::pickCanonical(
                    collect([$canonicalByIdentity[$identityKey], $subject]),
                );
            }
        }

        if ($canonicalByIdentity === []) {
            return;
        }

        $canonicalIds = collect($canonicalByIdentity)
            ->map(fn (ProcessSubject $subject): string => (string) $subject->id)
            ->values()
            ->all();

        foreach ($processes as $process) {
            $subjects = $process->subjects()->get();
            $detachIds = [];

            foreach ($subjects as $subject) {
                $identityKey = ProcessSubjectIdentityHelper::key($subject);
                $canonicalId = (string) $canonicalByIdentity[$identityKey]->id;

                if ((string) $subject->id !== $canonicalId) {
                    $detachIds[] = $subject->id;
                }
            }

            if ($detachIds !== []) {
                $process->subjects()->detach($detachIds);

                Log::channel($logChannel)->info('ProcessSyncService: removed duplicate subject links from instance', [
                    'process_id' => $process->id,
                    'subjects_removed' => count($detachIds),
                ]);
            }

            $existing = $process->subjects()->pluck('process_subjects.id')->map(fn ($value): string => (string) $value)->all();
            $missing = array_diff($canonicalIds, $existing);

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
     * True when API metadata reports activity newer than the newest actuación stored in DB.
     * In that state the daily inactive skip must not run — there are pending rows to import.
     */
    private function hasPendingActuacionesSync(Process $process): bool
    {
        if ($process->last_activity_date === null) {
            return false;
        }

        $dbMaxDate = $process->actions()->max('action_date');

        if ($dbMaxDate === null) {
            return true;
        }

        return Date::parse($process->last_activity_date)->startOfDay()
            ->gt(Date::parse($dbMaxDate)->startOfDay());
    }

    /**
     * Determine the earliest date from which notifications should be triggered
     * when a new process instance (with no existing actions) is synced during
     * the daily cron.
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
                    'status' => $isActive
                        ? \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus::ACTIVE->value
                        : \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus::INACTIVE->value,
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

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Src\Application\AppUser\Process\DTOs\RegisterProcessResult;
use Src\Application\Shared\Exceptions\ApiEmptyProcessesException;
use Src\Application\Shared\Exceptions\ApiForbiddenOrRateLimitException;
use Src\Application\Shared\Helpers\ProcessAlertLevelHelper;
use Src\Application\Shared\Process\Timeline\Contracts\ProcessTimelineRecorder;
use Src\Application\Shared\Process\Timeline\DTOs\RecordProcessTimelineEventData;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Application\Shared\Services\Process\ProcessSourceFallbackService;
use Src\Application\Shared\Services\Process\ProcessSyncService;
use Src\Application\Shared\Traits\ParseDateTrait;
use Src\Domain\AiChat\Models\AiChat;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessDataSource;
use Throwable;

readonly class RegisterProcessService
{
    use ParseDateTrait;

    public function __construct(
        private JudicialBranchConsultService $judicialBranchConsultService,
        private ProcessSyncService $processSyncService,
        private ProcessSourceFallbackService $processSourceFallbackService,
        private ProcessTimelineRecorder $timelineRecorder,
    ) {}

    /**
     * Registers a radicado for an organization.
     *
     * Fast path: if any instance of the radicado already exists in DB (registered by another org),
     * all instances are attached directly without any API call.
     * Full path: if the radicado is not in DB, the Portal Judicial API is consulted.
     *
     * @param  string  $processNumber  23-digit radicado number
     * @param  string  $organizationId  Organization UUID
     * @param  string  $proxySeed  Seed for proxy pool selection (e.g. processNumber:attempt)
     * @param  array<int, array<string, mixed>>|null  $prefetchedApiProcessesFromPortal  Resultado de fetchProcesses (procesos[]) cuando ya se resolvió en ProcessRegistrationSyncModeResolverService.
     *
     * @throws Throwable
     */
    public function handle(
        string $processNumber,
        string $organizationId,
        ?ProcessLawyerRole $lawyerRole = null,
        string $proxySeed = '',
        ?string $appUserId = null,
        ?array $prefetchedApiProcessesFromPortal = null,
        bool $deferRegistrationDigest = false,
    ): RegisterProcessResult {
        if ($proxySeed !== '') {
            $this->judicialBranchConsultService->withSeed($proxySeed);
        }

        $this->validateProcessNotAlreadyRegistered($processNumber, $organizationId);

        $existingProcesses = Process::query()->whereProcessNumber($processNumber)->get();

        if ($existingProcesses->isNotEmpty()) {
            return $this->attachExistingProcesses($existingProcesses, $organizationId, $lawyerRole, $appUserId, $deferRegistrationDigest);
        }

        return $this->registerFromApi($processNumber, $organizationId, $lawyerRole, $appUserId, $prefetchedApiProcessesFromPortal, $deferRegistrationDigest);
    }

    /**
     * Attaches all existing DB instances of a radicado to the organization.
     *
     * If the radicado still lives on Rama Judicial but is now private in the Portal,
     * migrates it to SAMAI first (global). The registering org sees data in the UI and
     * does not get a consolidado; sibling orgs get pending actuación rows for the next digest.
     *
     * @param  Collection<int, Process>  $processes  All DB instances of the radicado
     * @param  string  $organizationId  Organization UUID
     *
     * @throws Throwable
     */
    private function attachExistingProcesses(Collection $processes, string $organizationId, ?ProcessLawyerRole $lawyerRole, ?string $appUserId, bool $deferRegistrationDigest = false): RegisterProcessResult
    {
        $processNumber = (string) $processes->first()->process_number;

        $migratedToSamai = $this->detectPrivacyFlipAndMigrateToSamai($processNumber, $organizationId);

        // Recargar por si la fuente cambió a SAMAI.
        $processes = Process::query()->whereProcessNumber($processNumber)->get();

        $result = DB::transaction(function () use ($processes, $organizationId, $lawyerRole, $appUserId): RegisterProcessResult {
            /** @var Collection<int, Process> $attached */
            $attached = collect();
            $privateCount = 0;

            foreach ($processes as $process) {
                $process->loadMissing('processDataSource');

                // Tras migración exitosa la fuente es SAMAI e is_private=false.
                // Si sigue privado en JB (SAMAI no lo tenía), no se puede adjuntar.
                if ($process->is_private && $process->processDataSource?->slug === ProcessDataSourceSlug::JudicialBranch->value) {
                    $privateCount++;

                    continue;
                }

                $this->attachProcessToOrganization($process, $organizationId, $lawyerRole, $appUserId);
                $attached->push($process);
            }

            if ($attached->isEmpty()) {
                abort(422, __('process.all_instances_are_private'));
            }

            return new RegisterProcessResult(
                processes: $attached,
                hasMultipleInstances: $attached->count() > 1,
                totalProcesses: $processes->count(),
                registeredCount: $attached->count(),
                privateCount: $privateCount,
            );
        });

        // Si ya migró a SAMAI, los datos vienen del backfill: la org registrante no recibe consolidado.
        // Si sigue en JB público, sync de registro como antes (digest opcional).
        if (! $migratedToSamai) {
            $this->processSyncService->syncForRegistration(
                $processNumber,
                $organizationId,
                dispatchDigest: ! $deferRegistrationDigest,
            );
        }

        foreach ($result->processes as $process) {
            $process->refresh();
            $this->recalculateAlertLevelAfterSync($process, $organizationId, $lawyerRole);
        }

        return $result;
    }

    /**
     * Consulta Rama Judicial; si detecta público→privado (o ya privado pendiente),
     * marca el flip, avisa al admin y migra a SAMAI excluyendo notificaciones de digest
     * para la org que está registrando.
     */
    private function detectPrivacyFlipAndMigrateToSamai(string $processNumber, string $registeringOrganizationId): bool
    {
        $jbProcesses = Process::query()
            ->whereProcessNumber($processNumber)
            ->whereHas('processDataSource', fn (Builder $q) => $q->where('slug', ProcessDataSourceSlug::JudicialBranch->value))
            ->get();

        if ($jbProcesses->isEmpty()) {
            return false;
        }

        $privateFlipDetected = false;

        try {
            $this->judicialBranchConsultService->withSeed($processNumber);
            $result = $this->judicialBranchConsultService->fetchProcesses($processNumber);
        } catch (ApiEmptyProcessesException|ApiForbiddenOrRateLimitException) {
            $result = null;
        }

        if ($result !== null && $result->isSuccessful && $result->data !== []) {
            foreach ($result->data as $apiProceso) {
                $apiProcessId = (int) ($apiProceso['idProceso'] ?? 0);
                if ($apiProcessId === 0) {
                    continue;
                }

                $process = $jbProcesses->firstWhere('process_id', $apiProcessId);
                if ($process === null) {
                    continue;
                }

                $isNowPrivate = (bool) ($apiProceso['esPrivado'] ?? false);
                if ($isNowPrivate && ! $process->is_private) {
                    $occurredAt = now();

                    DB::transaction(function () use ($process, $occurredAt): void {
                        $process->update([
                            'is_private' => true,
                            'became_private_at' => $occurredAt,
                        ]);

                        $this->timelineRecorder->handle($process, new RecordProcessTimelineEventData(
                            eventType: ProcessTimelineEventType::PROCESS_BECAME_PRIVATE,
                            source: ProcessTimelineEventSource::JUDICIAL_BRANCH,
                            idempotencyKey: "privacy:{$process->id}:{$occurredAt->format('U.u')}",
                            payload: [
                                'from' => ['is_private' => false],
                                'to' => ['is_private' => true],
                                'reason' => 'judicial_branch_api_reported_private',
                            ],
                            subjectType: 'process',
                            subjectId: $process->id,
                            actorType: 'system',
                            occurredAt: $occurredAt,
                        ));
                    });

                    $privateFlipDetected = true;
                }
            }
        }

        // También reintentar si ya estaba marcado privado pero aún no migrado a SAMAI.
        $pendingPrivate = Process::query()
            ->whereProcessNumber($processNumber)
            ->where('is_private', true)
            ->whereNotNull('became_private_at')
            ->whereHas('processDataSource', fn (Builder $q) => $q->where('slug', ProcessDataSourceSlug::JudicialBranch->value))
            ->exists();

        if (! $privateFlipDetected && ! $pendingPrivate) {
            return false;
        }

        if ($privateFlipDetected) {
            // Correo solo al admin (no a organizaciones).
            $this->processSyncService->notifyPrivacyTransitionForAdmin($processNumber);
        }

        return $this->processSourceFallbackService->tryMigratePrivateJudicialToSamai(
            $processNumber,
            $registeringOrganizationId,
        );
    }

    /**
     * Consults the Portal Judicial API to register a new radicado and sync its actions/subjects.
     *
     * Called only when no instance of the radicado exists in DB.
     * For each API instance: if the process_id is already in DB (race condition), it attaches;
     * otherwise it creates the record and syncs actuaciones and sujetos procesales.
     *
     * @param  string  $processNumber  23-digit radicado number
     * @param  string  $organizationId  Organization UUID
     *
     * @throws Throwable
     */
    private function registerFromApi(
        string $processNumber,
        string $organizationId,
        ?ProcessLawyerRole $lawyerRole,
        ?string $appUserId,
        ?array $prefetchedApiProcessesFromPortal = null,
        bool $deferRegistrationDigest = false,
    ): RegisterProcessResult {
        $processesData = $this->validateAndGetProcessesFromPortalJudicial($processNumber, $prefetchedApiProcessesFromPortal);

        $hasMultipleInstances = count($processesData) > 1;
        $totalProcesses = count($processesData);

        /** @var Collection<int, Process> $registeredProcesses */
        $registeredProcesses = collect();
        $privateCount = 0;

        $result = DB::transaction(function () use ($processNumber, $organizationId, $lawyerRole, $appUserId, $processesData, $hasMultipleInstances, $totalProcesses, &$registeredProcesses, &$privateCount): RegisterProcessResult {
            foreach ($processesData as $processData) {
                $isPrivate = $processData['esPrivado'] ?? false;

                if ($isPrivate) {
                    $privateCount++;

                    continue;
                }

                $processId = $processData['idProceso'] ?? null;

                if (! $processId) {
                    continue;
                }

                $globalProcess = Process::query()->whereProcessId($processId)->first();

                if ($globalProcess) {
                    if ($globalProcess->is_private) {
                        $privateCount++;

                        continue;
                    }

                    $updateData = ['last_api_update' => now()];

                    if ($hasMultipleInstances && ! $globalProcess->has_multiple_instances) {
                        $updateData['has_multiple_instances'] = true;
                    }

                    $fechaUltimaActuacion = $this->parseDate($processData['fechaUltimaActuacion'] ?? null);
                    if ($fechaUltimaActuacion && ($globalProcess->last_activity_date === null || $fechaUltimaActuacion > $globalProcess->last_activity_date->format('Y-m-d'))) {
                        $updateData['last_activity_date'] = $fechaUltimaActuacion;
                    }

                    $globalProcess->update($updateData);

                    $this->attachProcessToOrganization($globalProcess, $organizationId, $lawyerRole, $appUserId);
                    $registeredProcesses->push($globalProcess);

                    continue;
                }

                $detailData = $this->validateAndGetProcessDetails($processId);
                $fechaUltimaActuacion = $processData['fechaUltimaActuacion'] ?? null;

                $process = $this->createProcess($processNumber, $processId, $detailData, $hasMultipleInstances, $fechaUltimaActuacion);
                $this->attachProcessToOrganization($process, $organizationId, $lawyerRole, $appUserId);

                $registeredProcesses->push($process);
            }

            if ($registeredProcesses->isEmpty()) {
                if ($totalProcesses === 1 && $privateCount === 1) {
                    abort(422, __('process.is_private'));
                }

                abort(422, __('process.all_instances_are_private'));
            }

            return new RegisterProcessResult(
                processes: $registeredProcesses,
                hasMultipleInstances: $hasMultipleInstances,
                totalProcesses: $totalProcesses,
                registeredCount: $registeredProcesses->count(),
                privateCount: $privateCount,
            );
        });

        $this->processSyncService->syncForRegistration(
            $processNumber,
            $organizationId,
            dispatchDigest: ! $deferRegistrationDigest,
        );

        foreach ($result->processes as $process) {
            $process->refresh();
            $this->recalculateAlertLevelAfterSync($process, $organizationId, $lawyerRole);
        }

        return $result;
    }

    /**
     * Create a new process record.
     *
     * @param  string|null  $fechaUltimaActuacion  From the list-by-number API (procesos[].fechaUltimaActuacion), not from detail.
     */
    private function createProcess(string $processNumber, int $processId, array $detailData, bool $hasMultipleInstances = false, ?string $fechaUltimaActuacion = null): Process
    {
        $processData = [
            'process_id' => $processId,
            'process_number' => $processNumber,
            'court' => $detailData['despacho'] ?? '',
            'speaker' => $detailData['ponente'] ?? null,
            'department' => $detailData['departamento'] ?? '',
            'process_type' => $detailData['tipoProceso'] ?? '',
            'process_class' => $detailData['claseProceso'] ?? '',
            'subclass_process' => $detailData['subclaseProceso'] ?? null,
            'litigants' => $detailData['sujetosProcesales'] ?? null,
            'process_date' => $this->parseDate($detailData['fechaProceso'] ?? null) ?? now()->toDateString(),
            'last_activity_date' => $this->parseDate($fechaUltimaActuacion),
            'location' => $detailData['ubicacion'] ?? null,
            'filing_content' => $detailData['contenidoRadicacion'] ?? null,
            'is_private' => $detailData['esPrivado'] ?? false,
            'has_multiple_instances' => $hasMultipleInstances,
            'last_api_update' => now(),
            'status' => 'activo',
            'is_manual_sync' => false,
            'process_data_source_id' => ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::JudicialBranch),
        ];

        return Process::query()->create($processData);
    }

    /**
     * Validate that the process is not already registered for the organization.
     *
     * @param  string  $processNumber  The process number to validate.
     * @param  string  $organizationId  The organization ID.
     */
    private function validateProcessNotAlreadyRegistered(string $processNumber, string $organizationId): void
    {
        $existingProcess = Process::query()
            ->whereProcessNumber($processNumber)
            ->whereHas('organizations', function (Builder $query) use ($organizationId): void {
                $query->where('organizations.id', $organizationId);
            })
            ->first();

        if ($existingProcess) {
            abort(422, __('process.already_registered'));
        }
    }

    /**
     * Validate that the process exists in the portal judicial and get all processes.
     *
     * @param  array<int, array<string, mixed>>|null  $prefetched  Cuando no es null, evita un segundo fetchProcesses.
     * @return array<int, array<string, mixed>> The processes data from the API.
     */
    private function validateAndGetProcessesFromPortalJudicial(string $processNumber, ?array $prefetched = null): array
    {
        if ($prefetched !== null) {
            if ($prefetched === []) {
                abort(404, __('process.not_found_in_judicial_branch'));
            }

            return $prefetched;
        }

        try {
            $response = $this->judicialBranchConsultService->fetchProcesses($processNumber);
        } catch (ApiEmptyProcessesException) {
            abort(404, __('process.not_found_in_judicial_branch'));
        }

        if (! $response->isSuccessful || empty($response->data)) {
            abort(404, __('process.not_found_in_judicial_branch'));
        }

        return $response->data;
    }

    /**
     * Validate and get detailed process information from the portal judicial.
     *
     * @param  int  $processId  The API process ID.
     * @return array<string, mixed> The detailed process data.
     *
     * @throws ApiForbiddenOrRateLimitException
     */
    private function validateAndGetProcessDetails(int $processId): array
    {
        $detailResponse = $this->judicialBranchConsultService->fetchDetailProcess($processId);

        if (! $detailResponse->isSuccessful || empty($detailResponse->data)) {
            abort(404, __('process.not_found_in_judicial_branch'));
        }

        return $detailResponse->data;
    }

    /**
     * Attach a process to an organization.
     *
     * @param  Process  $process  The process to attach.
     * @param  string  $organizationId  The organization ID.
     */
    private function attachProcessToOrganization(Process $process, string $organizationId, ?ProcessLawyerRole $lawyerRole, ?string $appUserId): void
    {
        $alertLevel = $this->calculateInitialAlertLevel($process, $lawyerRole);

        $process->organizations()->syncWithoutDetaching([
            $organizationId => [
                'interest_date' => now()->toDateString(),
                'is_active' => true,
                'status' => \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus::ACTIVE->value,
                'lawyer_role' => $lawyerRole?->value,
                'inactivity_alert_level' => $alertLevel,
            ],
        ]);

        if ($appUserId !== null && AppUser::query()->whereKey($appUserId)->exists()) {
            AiChat::query()->firstOrCreate([
                'process_id' => $process->id,
                'organization_id' => $organizationId,
            ], [
                'app_user_id' => $appUserId,
                'title' => 'Chat Inicial',
                'is_private' => false,
                'is_active' => true,
            ]);
        }
    }

    /**
     * After sync, last_activity_date is now real. Recalculate and persist the alert level.
     */
    private function recalculateAlertLevelAfterSync(Process $process, string $organizationId, ?ProcessLawyerRole $lawyerRole): void
    {
        if (! $lawyerRole || ! $process->last_activity_date) {
            return;
        }

        $alertLevel = $this->calculateInitialAlertLevel($process, $lawyerRole);

        DB::table('organization_processes')
            ->where('organization_id', $organizationId)
            ->where('process_id', $process->id)
            ->update(['inactivity_alert_level' => $alertLevel, 'updated_at' => now()]);
    }

    /**
     * Calculate initial alert level based on process activity.
     */
    private function calculateInitialAlertLevel(Process $process, ?ProcessLawyerRole $role): ?string
    {
        if (! $role || ! $process->last_activity_date) {
            return null;
        }

        return ProcessAlertLevelHelper::calculate(
            Date::parse($process->last_activity_date),
            $role,
        );
    }
}

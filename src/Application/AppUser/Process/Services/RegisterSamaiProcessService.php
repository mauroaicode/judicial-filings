<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Src\Application\AppUser\Process\DTOs\RegisterProcessResult;
use Src\Application\Shared\Helpers\ProcessAlertLevelHelper;
use Src\Application\Shared\Helpers\ProcessSubjectIdentityHelper;
use Src\Application\Shared\Helpers\SamaiCourtNameHelper;
use Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService;
use Src\Application\Shared\Services\Process\ProcessSyncService;
use Src\Application\Shared\Services\SamaiConsultService;
use Src\Application\Shared\Traits\MapsSamaiActuacionTrait;
use Src\Application\Shared\Traits\MapsSamaiSujetoTrait;
use Src\Application\Shared\Traits\ParseDateTrait;
use Src\Domain\AiChat\Models\AiChat;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessDataSource;
use Src\Domain\Process\Models\ProcessSubject;
use Throwable;

/**
 * Registra un proceso de SAMAI (Consejo de Estado) para una organización.
 *
 * Flujo:
 *  Fast path — si el radicado ya existe en DB (registrado por otra org), adjunta las instancias.
 *  Full path — si no existe, consulta la API de SAMAI, crea las instancias y sincroniza
 *              actuaciones y sujetos procesales.
 *
 * Diferencias clave respecto a RegisterProcessService (Rama Judicial):
 *  - Usa SamaiConsultService en lugar de JudicialBranchConsultService.
 *  - No hay process_id numérico; la instancia se identifica por process_number + samai_corporacion.
 *  - is_manual_sync = false, is_private = false, process_id = null.
 *  - Guarda el código de corporación SAMAI en la columna samai_corporacion.
 */
readonly class RegisterSamaiProcessService
{
    use MapsSamaiActuacionTrait;
    use MapsSamaiSujetoTrait;
    use ParseDateTrait;

    public function __construct(
        private SamaiConsultService $samaiService,
        private ProcessSyncService $processSyncService,
        private OrganizationProcessQuotaService $organizationProcessQuotaService,
    ) {}

    /**
     * Registra el radicado para la organización desde SAMAI.
     *
     * @throws Throwable
     */
    public function handle(
        string $processNumber,
        string $organizationId,
        ?ProcessLawyerRole $lawyerRole = null,
        string $proxySeed = '',
        ?string $appUserId = null,
        bool $deferRegistrationDigest = false,
        bool $queueRegistrationNotifications = true,
    ): RegisterProcessResult {
        if ($proxySeed !== '') {
            $this->samaiService->withSeed($proxySeed);
        }

        $this->validateProcessNotAlreadyRegistered($processNumber, $organizationId);
        $this->organizationProcessQuotaService->assertCanAddProcesses($organizationId);

        $existingProcesses = Process::query()->whereProcessNumber($processNumber)->get();

        if ($existingProcesses->isNotEmpty()) {
            $result = $this->attachExistingProcesses($existingProcesses, $organizationId, $lawyerRole, $appUserId);
            $this->processSyncService->finalizeSamaiRegistration(
                $processNumber,
                $organizationId,
                dispatchDigest: ! $deferRegistrationDigest,
                queueNotifications: $queueRegistrationNotifications,
            );

            return $result;
        }

        $result = $this->registerFromSamaiApi($processNumber, $organizationId, $lawyerRole, $appUserId);
        $this->processSyncService->finalizeSamaiRegistration(
            $processNumber,
            $organizationId,
            dispatchDigest: ! $deferRegistrationDigest,
            queueNotifications: $queueRegistrationNotifications,
        );

        return $result;
    }

    // -------------------------------------------------------------------------
    // Fast path
    // -------------------------------------------------------------------------

    /**
     * @param  Collection<int, Process>  $processes
     *
     * @throws Throwable
     */
    private function attachExistingProcesses(
        Collection $processes,
        string $organizationId,
        ?ProcessLawyerRole $lawyerRole,
        ?string $appUserId,
    ): RegisterProcessResult {
        return DB::transaction(function () use ($processes, $organizationId, $lawyerRole, $appUserId): RegisterProcessResult {
            /** @var Collection<int, Process> $attached */
            $attached = collect();
            $privateCount = 0;

            foreach ($processes as $process) {
                if ($process->is_private) {
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
        }, $this->registrationTransactionAttempts());
    }

    // -------------------------------------------------------------------------
    // Full path — registrar desde la API de SAMAI
    // -------------------------------------------------------------------------

    /**
     * @throws Throwable
     */
    private function registerFromSamaiApi(
        string $processNumber,
        string $organizationId,
        ?ProcessLawyerRole $lawyerRole,
        ?string $appUserId,
    ): RegisterProcessResult {
        $searchResults = $this->samaiService->buscarProceso($processNumber);

        if ($searchResults === []) {
            abort(404, __('process.not_found_in_samai'));
        }

        // Resolver corporaciones únicas a partir de los resultados de búsqueda
        $corporaciones = $this->resolveCorporaciones($searchResults, $processNumber);

        if ($corporaciones === []) {
            // Fallback: intentar derivar corporación de los primeros 7 dígitos
            $fallback = substr($processNumber, 0, 7);
            $corporaciones = [$fallback];
        }

        $hasMultipleInstances = count($corporaciones) > 1;
        $totalProcesses = count($corporaciones);

        /** @var Collection<int, Process> $registeredProcesses */
        $registeredProcesses = collect();

        $result = DB::transaction(function () use (
            $processNumber, $organizationId, $lawyerRole, $appUserId,
            $corporaciones, $hasMultipleInstances, $totalProcesses, &$registeredProcesses
        ): RegisterProcessResult {
            foreach ($corporaciones as $corporacion) {
                // ¿Ya existe en DB esta instancia SAMAI (mismo radicado + corporación)?
                $existing = $this->findExistingSamaiProcess($processNumber, $corporacion);

                if ($existing instanceof \Src\Domain\Process\Models\Process) {
                    if (! $existing->is_private) {
                        $this->attachProcessToOrganization($existing, $organizationId, $lawyerRole, $appUserId);
                        $registeredProcesses->push($existing);
                    }

                    continue;
                }

                $processData = $this->samaiService->obtenerDatosProceso($corporacion, $processNumber);

                $process = $this->createSamaiProcess($processNumber, $corporacion, $processData, $hasMultipleInstances);
                $this->attachProcessToOrganization($process, $organizationId, $lawyerRole, $appUserId);
                $this->syncActuaciones($process, $corporacion, $processNumber);
                $this->syncSujetos($process, $corporacion, $processNumber);

                $registeredProcesses->push($process);
            }

            if ($registeredProcesses->isEmpty()) {
                abort(422, __('process.not_found_in_samai'));
            }

            if ($hasMultipleInstances && $registeredProcesses->count() > 1) {
                Process::query()->where('process_number', $processNumber)
                    ->whereHas('processDataSource', fn (Builder $q) => $q->where('slug', ProcessDataSourceSlug::Samai->value))
                    ->update(['has_multiple_instances' => true]);
            }

            return new RegisterProcessResult(
                processes: $registeredProcesses,
                hasMultipleInstances: $hasMultipleInstances,
                totalProcesses: $totalProcesses,
                registeredCount: $registeredProcesses->count(),
                privateCount: 0,
            );
        }, $this->registrationTransactionAttempts());

        foreach ($result->processes as $process) {
            $process->refresh();
            $this->recalculateAlertLevelAfterSync($process, $organizationId, $lawyerRole);
        }

        return $result;
    }

    /**
     * Crea un nuevo proceso SAMAI en la base de datos.
     *
     * Los campos se mapean desde los campos reales que devuelve ObtenerDatosProcesoGet:
     *   - Origen / EntidadRadicadora → court (despacho)
     *   - Ponente → speaker (solo cuando es persona, no juzgado)
     *   - cityName → department/location
     *   - tipoProceso / claseProceso / claseProcesoComplemento1
     *   - Actor + Demandado → litigants
     *   - FECHAPROC → process_date
     *   - UltimaActuacionDespachoFecha → last_activity_date
     *
     * @param  array<string, mixed>  $processData  Campos del proceso (ya desenvuelto de la clave "proceso").
     */
    private function createSamaiProcess(
        string $processNumber,
        string $corporacion,
        array $processData,
        bool $hasMultipleInstances = false,
    ): Process {
        $isActive = strtoupper(trim((string) ($processData['Vigente'] ?? 'SI'))) === 'SI'
            && strtoupper(trim((string) ($processData['ProcesoArchivado'] ?? 'NO'))) !== 'SI';

        return Process::query()->create([
            'process_id' => null,
            'process_number' => $processNumber,
            'court' => $this->buildSamaiCourt($processData),
            'speaker' => $this->buildSamaiSpeaker($processData),
            'department' => trim((string) ($processData['cityName'] ?? '')),
            'process_type' => trim((string) ($processData['tipoProceso'] ?? '')),
            'process_class' => trim((string) ($processData['claseProceso'] ?? '')),
            'subclass_process' => $this->extractFieldNullable($processData, ['claseProcesoComplemento1', 'claseProcesoComplemento2']),
            'litigants' => $this->buildSamaiLitigants($processData),
            'process_date' => $this->parseDate(
                $this->extractFieldNullable($processData, ['FECHAPROC', 'FECHREGI'])
            ) ?? now()->toDateString(),
            'last_activity_date' => $this->buildSamaiLastActivityDate($processData),
            'location' => trim((string) ($processData['cityName'] ?? '')) ?: null,
            'filing_content' => null,
            'is_private' => false,
            'has_multiple_instances' => $hasMultipleInstances,
            'last_api_update' => now(),
            'status' => $isActive ? 'activo' : 'terminado',
            'is_manual_sync' => false,
            'process_data_source_id' => ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::Samai),
            'samai_corporacion' => $corporacion,
        ]);
    }

    /**
     * Despacho desde el campo "Origen" de SAMAI (fallback Ponente / sala).
     *
     * @param  array<string, mixed>  $processData
     */
    private function buildSamaiCourt(array $processData): string
    {
        return SamaiCourtNameHelper::build($processData);
    }

    /**
     * Devuelve el nombre del ponente solo cuando es una persona (no un despacho).
     *
     * @param  array<string, mixed>  $processData
     */
    private function buildSamaiSpeaker(array $processData): ?string
    {
        $ponente = trim((string) ($processData['Ponente'] ?? ''));

        if ($ponente === '') {
            return null;
        }

        // Si Ponente es el nombre del despacho (Juzgados), el speaker va vacío.
        if ($this->samaiPonenteIsCourtName($ponente)) {
            return null;
        }

        return $ponente;
    }

    private function samaiPonenteIsCourtName(string $ponente): bool
    {
        $normalized = mb_strtolower($ponente);

        return str_starts_with($normalized, 'juzgado')
            || str_starts_with($normalized, 'tribunal');
    }

    /**
     * Combina Actor y Demandado en un string legible.
     *
     * @param  array<string, mixed>  $processData
     */
    private function buildSamaiLitigants(array $processData): ?string
    {
        $actor = trim((string) ($processData['Actor'] ?? ''));
        $demandado = trim((string) ($processData['Demandado'] ?? ''));

        $parts = array_filter([$actor, $demandado], fn (string $v): bool => $v !== '');

        return $parts !== [] ? implode(' | ', $parts) : null;
    }

    /**
     * Determina la fecha de última actuación a partir de los campos del API.
     *
     * El API provee:
     *  - UltimaActuacionDespachoFecha:    "Jul 15 2026  9:30AM"  (formato irregular)
     *  - UltimaActuacionSecretariaFechas: "2026-07-14"           (puede ser múltiple, separado por coma)
     *
     * @param  array<string, mixed>  $processData
     */
    private function buildSamaiLastActivityDate(array $processData): ?string
    {
        $dates = [];

        $despachoRaw = $this->extractFieldNullable($processData, ['UltimaActuacionDespachoFecha']);
        if ($despachoRaw !== null) {
            $parsed = $this->parseDate(trim($despachoRaw));
            if ($parsed !== null) {
                $dates[] = $parsed;
            }
        }

        $secretariaRaw = trim((string) ($processData['UltimaActuacionSecretariaFechas'] ?? ''));
        if ($secretariaRaw !== '') {
            foreach (explode(',', $secretariaRaw) as $fechaStr) {
                $parsed = $this->parseDate(trim($fechaStr));
                if ($parsed !== null) {
                    $dates[] = $parsed;
                }
            }
        }

        if ($dates === []) {
            return null;
        }

        sort($dates);

        return end($dates);
    }

    /**
     * Sincroniza actuaciones SAMAI para un proceso recién creado.
     */
    private function syncActuaciones(Process $process, string $corporacion, string $processNumber): void
    {
        $result = $this->samaiService->obtenerActuaciones($corporacion, $processNumber);

        if (! $result->isSuccessful || $result->data === []) {
            return;
        }

        $maxActivityDate = null;

        foreach ($result->data as $apiActuacion) {
            $orden = $this->samaiActuacionOrden($apiActuacion);
            if ($orden === 0) {
                continue;
            }

            if (ProcessAction::query()->existsByActionRegistrationId($process->id, $orden)) {
                continue;
            }

            $attributes = $this->mapSamaiActuacionToAttributes($apiActuacion);
            $attributes['process_id'] = $process->id;

            ProcessAction::query()->create($attributes);

            $actionDateStr = $attributes['action_date'];
            if ($maxActivityDate === null || $actionDateStr > $maxActivityDate) {
                $maxActivityDate = $actionDateStr;
            }
        }

        $updateData = ['last_api_update' => now()];
        if ($maxActivityDate !== null) {
            $currentLa = $process->last_activity_date?->format('Y-m-d');
            if ($currentLa === null || $maxActivityDate > $currentLa) {
                $updateData['last_activity_date'] = $maxActivityDate;
            }
        }

        $process->update($updateData);
    }

    /**
     * Sincroniza sujetos procesales SAMAI para un proceso recién creado.
     */
    private function syncSujetos(Process $process, string $corporacion, string $processNumber): void
    {
        $result = $this->samaiService->obtenerSujetosProcesales($corporacion, $processNumber);

        if (! $result->isSuccessful || $result->data === []) {
            return;
        }

        foreach ($result->data as $apiSujeto) {
            $attributes = $this->mapSamaiSujetoToAttributes($apiSujeto);

            if ($attributes['name_or_business_name'] === '') {
                continue;
            }

            $subject = ProcessSubjectIdentityHelper::findCanonicalForRadicado(
                $process->process_number,
                $attributes['subject_type'],
                $attributes['name_or_business_name'],
                $attributes['identification'],
            );

            if (! $subject instanceof ProcessSubject) {
                $subject = ProcessSubject::query()->create($attributes);
            }

            $process->subjects()->syncWithoutDetaching([$subject->id]);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Adjunta un proceso a una organización creando el pivot si no existe.
     */
    private function attachProcessToOrganization(Process $process, string $organizationId, ?ProcessLawyerRole $lawyerRole, ?string $appUserId): void
    {
        $alertLevel = $this->calculateInitialAlertLevel($process, $lawyerRole);

        OrganizationProcess::syncActiveLink($organizationId, $process->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
            'status' => OrganizationProcessStatus::ACTIVE,
            'lawyer_role' => $lawyerRole,
            'inactivity_alert_level' => $alertLevel,
        ]);

        if ($appUserId !== null && AppUser::query()->whereKey($appUserId)->exists()) {
            AiChat::query()->firstOrCreate(
                ['process_id' => $process->id, 'organization_id' => $organizationId],
                [
                    'app_user_id' => $appUserId,
                    'title' => 'Chat Inicial',
                    'is_private' => false,
                    'is_active' => true,
                ]
            );
        }
    }

    private function validateProcessNotAlreadyRegistered(string $processNumber, string $organizationId): void
    {
        $exists = Process::query()
            ->whereProcessNumber($processNumber)
            ->whereHas('organizations', fn (Builder $q) => $q->where('organizations.id', $organizationId))
            ->exists();

        if ($exists) {
            abort(422, __('process.already_registered'));
        }
    }

    /**
     * Busca una instancia SAMAI existente identificada por radicado + corporación.
     */
    private function findExistingSamaiProcess(string $processNumber, string $corporacion): ?Process
    {
        return Process::query()
            ->whereProcessNumber($processNumber)
            ->where('samai_corporacion', $corporacion)
            ->whereHas('processDataSource', fn (Builder $q) => $q->where('slug', ProcessDataSourceSlug::Samai->value))
            ->first();
    }

    /**
     * Extrae corporaciones únicas del resultado de BuscarProcesoTodoSamai.
     * Si un resultado no trae corporación, usa los 7 primeros dígitos del radicado.
     *
     * @param  array<int, array<string, mixed>>  $searchResults
     * @return list<string>
     */
    private function resolveCorporaciones(array $searchResults, string $processNumber): array
    {
        $seen = [];
        $out = [];

        foreach ($searchResults as $result) {
            $corp = $this->samaiService->extractCorporacion($result, $processNumber);
            if ($corp !== '' && ! isset($seen[$corp])) {
                $seen[$corp] = true;
                $out[] = $corp;
            }
        }

        return $out;
    }

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
     * @param  list<string>  $keys
     */
    private function extractFieldNullable(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '') {
                return trim($data[$key]);
            }
        }

        return null;
    }

    private function registrationTransactionAttempts(): int
    {
        return max(1, (int) config('judicial-branch.registration_db_transaction_attempts', 5));
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Helpers\ProcessAlertLevelHelper;
use Src\Application\Shared\Helpers\SamaiCourtNameHelper;
use Src\Application\Shared\Process\Timeline\Contracts\ProcessTimelineRecorder;
use Src\Application\Shared\Process\Timeline\DTOs\RecordProcessTimelineEventData;
use Src\Application\Shared\Process\Timeline\Services\RecordSemaphoreTimelineEventService;
use Src\Application\Shared\Services\SamaiConsultService;
use Src\Application\Shared\Traits\MapsSamaiActuacionTrait;
use Src\Application\Shared\Traits\MapsSamaiSujetoTrait;
use Src\Application\Shared\Traits\ParseDateTrait;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessDataSource;
use Src\Domain\Process\Models\ProcessSubject;

/**
 * Cadena de fallback de fuentes de datos.
 *
 * Cuando un proceso deja de ser accesible en Rama Judicial (se marca como privado),
 * este servicio intenta encontrarlo en las fuentes alternativas en orden:
 *
 *  Rama Judicial → SAMAI (Consejo de Estado) → (TYBA — futuro)
 *
 * Si se localiza en una fuente alternativa, el registro de `processes` se migra
 * in-place: se actualizan la fuente, el corporacion SAMAI, is_private y process_id.
 * Las actuaciones y sujetos se sincronizan desde la nueva fuente.
 *
 * El registro histórico de `became_private_at` no se borra; refleja el momento
 * en que la Rama Judicial reportó el proceso como privado.
 */
readonly class ProcessSourceFallbackService
{
    use MapsSamaiActuacionTrait;
    use MapsSamaiSujetoTrait;
    use ParseDateTrait;

    public function __construct(
        private SamaiConsultService $samaiService,
        private ProcessActionAlertNotificationService $alertNotificationService,
        private ProcessTimelineRecorder $timelineRecorder,
        private RecordSemaphoreTimelineEventService $recordSemaphoreTimelineEventService,
    ) {}

    /**
     * Intenta migrar todos los procesos privados de un radicado (fuente Rama Judicial) a SAMAI.
     *
     * @param  string|null  $excludeOrganizationIdFromActuacionNotifications  Org que acaba de registrar
     *                                                                        (ve los datos en UI): no se le
     *                                                                        crean filas de digest. Las demás
     *                                                                        orgs quedan con is_email_notified=false
     *                                                                        para el próximo consolidado.
     * @return bool true si se migró al menos un proceso
     */
    public function tryMigratePrivateJudicialToSamai(
        string $processNumber,
        ?string $excludeOrganizationIdFromActuacionNotifications = null,
    ): bool {
        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        // Obtener los procesos privados de Rama Judicial para este radicado.
        $privateProcesses = Process::query()
            ->whereProcessNumber($processNumber)
            ->where('is_private', true)
            ->whereNotNull('became_private_at')
            ->whereHas('processDataSource', fn (Builder $q) => $q->where('slug', ProcessDataSourceSlug::JudicialBranch->value))
            ->get();

        if ($privateProcesses->isEmpty()) {
            Log::channel($channel)->info('ProcessSourceFallbackService: no private JB processes to migrate', [
                'process_number' => $processNumber,
            ]);

            return false;
        }

        $this->samaiService->withSeed($processNumber);

        $searchResults = $this->samaiService->buscarProceso($processNumber);

        if ($searchResults === []) {
            Log::channel($channel)->info('ProcessSourceFallbackService: not found in SAMAI — process remains private', [
                'process_number' => $processNumber,
            ]);

            return false;
        }

        // Extraer corporaciones únicas desde los resultados de búsqueda.
        $corporaciones = $this->resolveCorporaciones($searchResults, $processNumber);

        if ($corporaciones === []) {
            $corporaciones = [substr($processNumber, 0, 7)];
        }

        $migrated = 0;
        $privateQueue = $privateProcesses->values();

        foreach ($corporaciones as $index => $corporacion) {
            // ¿Ya existe un registro SAMAI para este radicado+corporación?
            $alreadyMigrated = Process::query()
                ->whereProcessNumber($processNumber)
                ->where('samai_corporacion', $corporacion)
                ->exists();

            if ($alreadyMigrated) {
                continue;
            }

            // Obtener datos del proceso desde SAMAI.
            // obtenerDatosProceso devuelve array con los campos del proceso (ya desenvuelto de "proceso"), o [].
            $data = $this->samaiService->obtenerDatosProceso($corporacion, $processNumber);

            if (isset($privateQueue[$index])) {
                // Actualizar el registro de Rama Judicial existente para convertirlo en SAMAI.
                $process = $privateQueue[$index];
                $this->migrateProcessToSamai($process, $corporacion, $data, $channel);
            } else {
                // Más corporaciones en SAMAI que instancias privadas en JB → crear nuevo registro.
                $process = $this->createSamaiProcess($processNumber, $corporacion, $data, count($corporaciones) > 1);
                // Vincular a las mismas organizaciones que tienen activo el radicado.
                $this->inheritOrganizationLinks($processNumber, $process);
            }

            // Sincronizar actuaciones desde SAMAI.
            $this->syncActuaciones(
                $process,
                $corporacion,
                $processNumber,
                $excludeOrganizationIdFromActuacionNotifications,
            );
            $this->syncSujetos($process, $corporacion, $processNumber);

            $migrated++;

            Log::channel($channel)->info('ProcessSourceFallbackService: migrated process to SAMAI', [
                'process_number' => $processNumber,
                'corporacion' => $corporacion,
                'process_id' => $process->id,
            ]);
        }

        if ($migrated > 0 && count($corporaciones) > 1) {
            Process::query()
                ->whereProcessNumber($processNumber)
                ->whereHas('processDataSource', fn (Builder $q) => $q->where('slug', ProcessDataSourceSlug::Samai->value))
                ->update(['has_multiple_instances' => true]);
        }

        return $migrated > 0;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Actualiza in-place un proceso de Rama Judicial para que pase a ser SAMAI.
     *
     * @param  array<string, mixed>  $data
     */
    private function migrateProcessToSamai(Process $process, string $corporacion, array $data, string $channel): void
    {
        $updateData = [
            'process_data_source_id' => ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::Samai),
            'samai_corporacion' => $corporacion,
            'is_private' => false,
            'process_id' => null,  // SAMAI no usa ID numérico
            'is_manual_sync' => false,
            'last_api_update' => now(),
        ];

        if ($data !== []) {
            $updateData['court'] = $this->buildSamaiCourt($data) ?: $process->court;
            $updateData['speaker'] = $this->buildSamaiSpeaker($data);
        }

        $occurredAt = now();

        DB::transaction(function () use ($process, $updateData, $corporacion, $occurredAt): void {
            $previousExternalProcessId = $process->process_id;

            $process->update($updateData);

            $this->timelineRecorder->handle($process, new RecordProcessTimelineEventData(
                eventType: ProcessTimelineEventType::PROCESS_SOURCE_CHANGED,
                source: ProcessTimelineEventSource::SYSTEM,
                idempotencyKey: "source:{$process->id}:samai:{$corporacion}",
                payload: [
                    'from' => [
                        'data_source' => ProcessDataSourceSlug::JudicialBranch->value,
                        'external_process_id' => $previousExternalProcessId,
                    ],
                    'to' => [
                        'data_source' => ProcessDataSourceSlug::Samai->value,
                        'samai_corporacion' => $corporacion,
                    ],
                    'migration' => 'in_place',
                ],
                subjectType: 'process',
                subjectId: $process->id,
                actorType: 'job',
                occurredAt: $occurredAt,
            ));
        });

        Log::channel($channel)->info('ProcessSourceFallbackService: JB process migrated to SAMAI in-place', [
            'process_uuid' => $process->id,
            'corporacion' => $corporacion,
        ]);
    }

    /**
     * Crea un nuevo proceso SAMAI cuando hay más corporaciones que instancias JB privadas.
     *
     * @param  array<string, mixed>  $data  Campos del proceso (ya desenvuelto de la clave "proceso").
     */
    private function createSamaiProcess(
        string $processNumber,
        string $corporacion,
        array $data,
        bool $hasMultipleInstances,
    ): Process {
        $isActive = strtoupper(trim((string) ($data['Vigente'] ?? 'SI'))) === 'SI'
            && strtoupper(trim((string) ($data['ProcesoArchivado'] ?? 'NO'))) !== 'SI';

        return Process::query()->create([
            'process_id' => null,
            'process_number' => $processNumber,
            'court' => $this->buildSamaiCourt($data),
            'speaker' => $this->buildSamaiSpeaker($data),
            'department' => trim((string) ($data['cityName'] ?? '')),
            'process_type' => trim((string) ($data['tipoProceso'] ?? '')),
            'process_class' => trim((string) ($data['claseProceso'] ?? '')),
            'subclass_process' => $this->extractFieldNullable($data, ['claseProcesoComplemento1', 'claseProcesoComplemento2']),
            'litigants' => $this->buildSamaiLitigants($data),
            'process_date' => $this->parseDate(
                $this->extractFieldNullable($data, ['FECHAPROC', 'FECHREGI'])
            ) ?? now()->toDateString(),
            'last_activity_date' => $this->buildSamaiLastActivityDate($data),
            'location' => trim((string) ($data['cityName'] ?? '')) ?: null,
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
     * Hereda los vínculos de organizaciones activas que ya tiene el radicado en DB.
     */
    private function inheritOrganizationLinks(string $processNumber, Process $newProcess): void
    {
        $rows = OrganizationProcess::query()
            ->join('processes', 'organization_processes.process_id', '=', 'processes.id')
            ->where('processes.process_number', $processNumber)
            ->where('organization_processes.is_active', true)
            ->select('organization_processes.organization_id', 'organization_processes.lawyer_role')
            ->get();

        foreach ($rows as $row) {
            OrganizationProcess::query()->firstOrCreate(
                [
                    'organization_id' => $row->organization_id,
                    'process_id' => $newProcess->id,
                ],
                [
                    'interest_date' => now()->toDateString(),
                    'is_active' => true,
                    'status' => OrganizationProcessStatus::ACTIVE->value,
                    'lawyer_role' => $row->lawyer_role,
                ]
            );
        }
    }

    private function syncActuaciones(
        Process $process,
        string $corporacion,
        string $processNumber,
        ?string $excludeOrganizationIdFromActuacionNotifications = null,
    ): void {
        $result = $this->samaiService->obtenerActuaciones($corporacion, $processNumber);

        if (! $result->isSuccessful || $result->data === []) {
            return;
        }

        $maxActionDate = null;
        $hasNewActions = false;
        $latestNewAction = null;

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

            $action = ProcessAction::query()->create($attributes);
            $hasNewActions = true;
            $latestNewAction = $action;

            // Orgs existentes: filas pendientes para el próximo consolidado (no se despacha digest aquí).
            // La org que acaba de registrar se excluye: ve los datos en la UI al momento.
            $this->queuePendingActuacionNotificationsForSiblingOrgs(
                $action,
                $process,
                $excludeOrganizationIdFromActuacionNotifications,
            );

            $dateStr = $attributes['action_date'];
            if ($maxActionDate === null || $dateStr > $maxActionDate) {
                $maxActionDate = $dateStr;
            }
        }

        $updateData = ['last_api_update' => now()];
        if ($maxActionDate !== null) {
            $current = $process->last_activity_date?->format('Y-m-d');
            if ($current === null || $maxActionDate > $current) {
                $updateData['last_activity_date'] = $maxActionDate;
            }
        }

        $process->update($updateData);

        if ($hasNewActions && $latestNewAction instanceof ProcessAction) {
            DB::transaction(function () use ($process, $latestNewAction): void {
                $organizationProcesses = OrganizationProcess::query()
                    ->where('process_id', $process->id)
                    ->whereNotNull('inactivity_alert_level')
                    ->get();

                foreach ($organizationProcesses as $organizationProcess) {
                    $previousLevel = $organizationProcess->inactivity_alert_level;
                    $effectiveLevel = ProcessAlertLevelHelper::resolve(
                        null,
                        $process->last_activity_date,
                        $organizationProcess->lawyer_role,
                    );

                    OrganizationProcess::query()
                        ->where('organization_id', $organizationProcess->organization_id)
                        ->where('process_id', $organizationProcess->process_id)
                        ->update(['inactivity_alert_level' => null]);

                    $this->recordSemaphoreTimelineEventService->handle(
                        process: $process,
                        organizationId: $organizationProcess->organization_id,
                        from: $previousLevel,
                        to: $effectiveLevel,
                        reason: 'new_judicial_action',
                        lawyerRole: $organizationProcess->lawyer_role?->value,
                        source: ProcessTimelineEventSource::SAMAI,
                        subjectType: 'process_action',
                        subjectId: $latestNewAction->id,
                        action: $latestNewAction,
                    );
                }
            });
        }
    }

    /**
     * Crea OrganizationNotification (is_email_notified=false) para orgs activas excepto la excluida.
     * Salen en el próximo DispatchOrganizationDigestsJob (fin de sync diario).
     */
    private function queuePendingActuacionNotificationsForSiblingOrgs(
        ProcessAction $action,
        Process $process,
        ?string $excludeOrganizationId,
    ): void {
        $organizations = $process->organizations()
            ->wherePivot('is_active', true)
            ->get();

        foreach ($organizations as $organization) {
            if ($excludeOrganizationId !== null && $organization->id === $excludeOrganizationId) {
                continue;
            }

            $this->alertNotificationService->handleForOrganization(
                $action,
                $process,
                $organization->id,
                'actuacion',
            );
        }
    }

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

            $subject = \Src\Application\Shared\Helpers\ProcessSubjectIdentityHelper::findCanonicalForRadicado(
                $process->process_number,
                $attributes['subject_type'],
                $attributes['name_or_business_name'],
                $attributes['identification'],
            );

            if (! $subject instanceof ProcessSubject) {
                $subject = ProcessSubject::query()->firstOrCreate(
                    ['subject_registration_id' => null, 'name_or_business_name' => $attributes['name_or_business_name'], 'subject_type' => $attributes['subject_type']],
                    $attributes
                );
            }

            $process->subjects()->syncWithoutDetaching([$subject->id]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $searchResults
     * @return list<string>
     */
    private function resolveCorporaciones(array $searchResults, string $processNumber): array
    {
        $corporaciones = [];

        foreach ($searchResults as $result) {
            $corporacion = $this->samaiService->extractCorporacion($result, $processNumber);

            if ($corporacion !== '' && ! in_array($corporacion, $corporaciones, true)) {
                $corporaciones[] = $corporacion;
            }
        }

        return $corporaciones;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildSamaiCourt(array $data): string
    {
        return SamaiCourtNameHelper::build($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildSamaiSpeaker(array $data): ?string
    {
        $ponente = trim((string) ($data['Ponente'] ?? ''));

        if ($ponente === '') {
            return null;
        }

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
     * @param  array<string, mixed>  $data
     */
    private function buildSamaiLitigants(array $data): ?string
    {
        $actor = trim((string) ($data['Actor'] ?? ''));
        $demandado = trim((string) ($data['Demandado'] ?? ''));

        $parts = array_filter([$actor, $demandado], fn (string $v): bool => $v !== '');

        return $parts !== [] ? implode(' | ', $parts) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildSamaiLastActivityDate(array $data): ?string
    {
        $dates = [];

        $despachoRaw = $this->extractFieldNullable($data, ['UltimaActuacionDespachoFecha']);
        if ($despachoRaw !== null) {
            $parsed = $this->parseDate(trim($despachoRaw));
            if ($parsed !== null) {
                $dates[] = $parsed;
            }
        }

        $secretariaRaw = trim((string) ($data['UltimaActuacionSecretariaFechas'] ?? ''));
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
     * @param  array<string, mixed>  $data
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
}

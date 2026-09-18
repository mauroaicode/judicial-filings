<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;
use Src\Application\AppUser\Process\DTOs\SmartProcessRoutingDecision;
use Src\Application\Shared\Exceptions\ApiEmptyProcessesException;
use Src\Application\Shared\Exceptions\ApiForbiddenOrRateLimitException;
use Src\Application\Shared\Exceptions\ApiProxyFailureException;
use Src\Application\Shared\Exceptions\ManualRegistrationRequiredException;
use Src\Application\Shared\Exceptions\SamaiDiscoveryTimeoutException;
use Src\Application\Shared\Helpers\ProcessConsultationScopeHelper;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Application\Shared\Services\SamaiConsultService;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;
use Src\Domain\Process\Enums\ManualRegistrationRequestReason;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Models\Process;

/**
 * Detecta automáticamente la fuente de datos correcta para un radicado y decide
 * si el alta debe ejecutarse inline (pocos actuaciones) o ir a cola (historial largo).
 *
 * Orden de prioridad:
 *  1. Rama Judicial  → procesos públicos en el Portal CPNU.
 *  2. SAMAI          → solo juzgados/tribunales administrativos y Consejo de Estado
 *                      (si Unificada no lo tiene o está privado).
 *  (3. TYBA          → pendiente de integración futura.)
 *
 * Si el radicado ya existe en la BD (de cualquier fuente), se usa el fast-path
 * inline sin necesidad de llamar a ninguna API. Los privados existentes piden
 * alta manual (modal) aunque haya un sync diario activo.
 *
 * Un batch de sync diario no salta la clasificación (consulta Portal / SAMAI):
 * solo encola el alta pesada de instancias públicas para no pelear locks MySQL.
 */
readonly class SmartProcessRegistrationResolverService
{
    public function __construct(
        private JudicialBranchConsultService $judicialBranchService,
        private SamaiConsultService $samaiService,
    ) {}

    public function handle(string $processNumber, string $organizationId): SmartProcessRoutingDecision
    {
        $this->assertNotAlreadyRegisteredForOrganization($processNumber, $organizationId);

        // Fast path: el proceso ya existe en la BD (otra organización lo registró antes).
        $existing = Process::query()->whereProcessNumber($processNumber)->get();
        if ($existing->isNotEmpty()) {
            return $this->fastPathDecision($existing);
        }

        // Intentar Rama Judicial primero (procesos públicos).
        // Si hay batch diario activo, igual se clasifica (privado → modal);
        // solo se encola el write pesado de instancias públicas.
        $jbDecision = $this->tryJudicialBranch($processNumber);
        if ($jbDecision instanceof SmartProcessRoutingDecision) {
            return $jbDecision;
        }

        // Rama Judicial no lo encontró o todos los registros son privados.
        // SAMAI solo aplica a juzgados/tribunales administrativos y Consejo de Estado.
        if (! ProcessConsultationScopeHelper::shouldConsultSamai($processNumber)) {
            throw new ManualRegistrationRequiredException(
                $processNumber,
                ManualRegistrationRequestReason::NotFound,
            );
        }

        $samaiDecision = $this->trySamai($processNumber);
        if ($samaiDecision instanceof SmartProcessRoutingDecision) {
            return $samaiDecision;
        }

        throw new ManualRegistrationRequiredException(
            $processNumber,
            ManualRegistrationRequestReason::NotFound,
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @param  Collection<int, Process>  $existing
     */
    private function fastPathDecision(Collection $existing): SmartProcessRoutingDecision
    {
        $public = $existing->first(fn (Process $process): bool => ! $process->is_private);

        if (! $public instanceof Process) {
            throw new ManualRegistrationRequiredException(
                (string) $existing->first()->process_number,
                $existing->count() === 1
                    ? ManualRegistrationRequestReason::Private
                    : ManualRegistrationRequestReason::AllPrivate,
            );
        }

        $public->loadMissing('processDataSource');
        $sourceSlug = $public->processDataSource?->slug;

        $source = $sourceSlug === ProcessDataSourceSlug::Samai->value
            ? ProcessDataSourceSlug::Samai
            : ProcessDataSourceSlug::JudicialBranch;

        return new SmartProcessRoutingDecision(
            source: $source,
            deferToQueue: false,
        );
    }

    private function tryJudicialBranch(string $processNumber): ?SmartProcessRoutingDecision
    {
        $this->judicialBranchService->withSeed($processNumber);

        try {
            $response = $this->judicialBranchService->fetchProcesses($processNumber);
        } catch (ApiEmptyProcessesException) {
            return null;
        } catch (ApiProxyFailureException|ApiForbiddenOrRateLimitException) {
            // Portal/proxy no disponible en el request: no bloquear al abogado.
            // Misma puerta que SAMAI timeout → cola process-import reintenta.
            return new SmartProcessRoutingDecision(
                source: ProcessDataSourceSlug::JudicialBranch,
                deferToQueue: true,
            );
        }

        if (! $response->isSuccessful || $response->data === []) {
            return null;
        }

        /** @var array<int, array<string, mixed>> $processesData */
        $processesData = $response->data;

        // Verificar si hay al menos una instancia no-privada.
        $hasPublicInstance = false;
        foreach ($processesData as $processData) {
            if (! ($processData['esPrivado'] ?? false)) {
                $hasPublicInstance = true;
                break;
            }
        }

        if (! $hasPublicInstance) {
            // Todos son privados en Rama Judicial → dejar que SAMAI intente.
            return null;
        }

        // Batch diario activo: ya sabemos que es público; no peek de páginas (otra ida al proxy).
        if (JudicialSyncRun::hasActiveBatch(JudicialSyncDataSource::JudicialBranch)) {
            return new SmartProcessRoutingDecision(
                source: ProcessDataSourceSlug::JudicialBranch,
                deferToQueue: true,
                prefetchedJbProcesses: $processesData,
            );
        }

        // Determinar si ir a cola o inline contando páginas de actuaciones para instancias nuevas.
        $inlineMaxPages = max(1, (int) config('judicial-branch.registration_inline_max_actuacion_pages', 2));
        $maxPages = 1;

        foreach ($processesData as $processData) {
            if ($processData['esPrivado'] ?? false) {
                continue;
            }

            $processId = (int) ($processData['idProceso'] ?? 0);
            if ($processId === 0) {
                continue;
            }

            if (Process::query()->whereProcessId($processId)->exists()) {
                continue;
            }

            $peek = $this->judicialBranchService->peekActuacionesPagination($processId);
            if ($peek->isSuccessful) {
                $maxPages = max($maxPages, $peek->totalPages);
            }
        }

        return new SmartProcessRoutingDecision(
            source: ProcessDataSourceSlug::JudicialBranch,
            deferToQueue: $maxPages > $inlineMaxPages,
            prefetchedJbProcesses: $processesData,
        );
    }

    private function trySamai(string $processNumber): ?SmartProcessRoutingDecision
    {
        $this->samaiService->withSeed($processNumber);

        try {
            $searchResults = $this->samaiService->buscarProceso($processNumber);
        } catch (SamaiDiscoveryTimeoutException) {
            // SAMAI tardó demasiado — no es un 404 real.
            // Enviar a cola para que el job reintente con su propio backoff.
            return new SmartProcessRoutingDecision(
                source: ProcessDataSourceSlug::Samai,
                deferToQueue: true,
            );
        }

        if ($searchResults === []) {
            return null;
        }

        if (JudicialSyncRun::hasActiveBatch(JudicialSyncDataSource::Samai)) {
            return new SmartProcessRoutingDecision(
                source: ProcessDataSourceSlug::Samai,
                deferToQueue: true,
            );
        }

        $inlineMax = max(1, (int) config('samai.registration_inline_max_actuaciones', 50));
        $deferToQueue = false;

        foreach ($searchResults as $result) {
            $corporacion = $this->samaiService->extractCorporacion($result, $processNumber);

            if ($corporacion === '') {
                continue;
            }

            if (Process::query()->whereProcessNumber($processNumber)->where('samai_corporacion', $corporacion)->exists()) {
                continue;
            }

            $count = $this->samaiService->contarActuaciones($corporacion, $processNumber);
            if ($count > $inlineMax) {
                $deferToQueue = true;
                break;
            }
        }

        return new SmartProcessRoutingDecision(
            source: ProcessDataSourceSlug::Samai,
            deferToQueue: $deferToQueue,
        );
    }

    private function assertNotAlreadyRegisteredForOrganization(string $processNumber, string $organizationId): void
    {
        $exists = Process::query()
            ->whereProcessNumber($processNumber)
            ->whereHas('organizations', function (Builder $query) use ($organizationId): void {
                $query->where('organizations.id', $organizationId);
            })
            ->exists();

        if ($exists) {
            abort(422, __('process.already_registered'));
        }
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Src\Application\AppUser\Process\DTOs\SmartProcessRoutingDecision;
use Src\Application\Shared\Exceptions\ApiEmptyProcessesException;
use Src\Application\Shared\Exceptions\SamaiDiscoveryTimeoutException;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Application\Shared\Services\SamaiConsultService;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Models\Process;

/**
 * Detecta automáticamente la fuente de datos correcta para un radicado y decide
 * si el alta debe ejecutarse inline (pocos actuaciones) o ir a cola (historial largo).
 *
 * Orden de prioridad:
 *  1. Rama Judicial  → procesos públicos en el Portal CPNU.
 *  2. SAMAI          → Consejo de Estado, procesos privados/restringidos.
 *  (3. TYBA          → pendiente de integración futura.)
 *
 * Si el radicado ya existe en la BD (de cualquier fuente), se usa el fast-path
 * inline sin necesidad de llamar a ninguna API.
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
        $existing = Process::query()->whereProcessNumber($processNumber)->first();
        if ($existing !== null) {
            return $this->fastPathDecision($existing);
        }

        // Intentar Rama Judicial primero (procesos públicos).
        $jbDecision = $this->tryJudicialBranch($processNumber);
        if ($jbDecision instanceof \Src\Application\AppUser\Process\DTOs\SmartProcessRoutingDecision) {
            return $jbDecision;
        }

        // Rama Judicial no lo encontró o todos los registros son privados → probar SAMAI.
        $samaiDecision = $this->trySamai($processNumber);
        if ($samaiDecision instanceof \Src\Application\AppUser\Process\DTOs\SmartProcessRoutingDecision) {
            return $samaiDecision;
        }

        abort(404, __('process.not_found_in_any_source'));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function fastPathDecision(Process $existing): SmartProcessRoutingDecision
    {
        $existing->loadMissing('processDataSource');
        $sourceSlug = $existing->processDataSource?->slug;

        $source = $sourceSlug === ProcessDataSourceSlug::Samai->value
            ? ProcessDataSourceSlug::Samai
            : ProcessDataSourceSlug::JudicialBranch;

        $syncSource = $source === ProcessDataSourceSlug::Samai
            ? JudicialSyncDataSource::Samai
            : JudicialSyncDataSource::JudicialBranch;

        return new SmartProcessRoutingDecision(
            source: $source,
            deferToQueue: JudicialSyncRun::hasActiveBatch($syncSource),
        );
    }

    private function tryJudicialBranch(string $processNumber): ?SmartProcessRoutingDecision
    {
        $this->judicialBranchService->withSeed($processNumber);

        try {
            $response = $this->judicialBranchService->fetchProcesses($processNumber);
        } catch (ApiEmptyProcessesException) {
            return null;
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
            deferToQueue: $maxPages > $inlineMaxPages
                || JudicialSyncRun::hasActiveBatch(JudicialSyncDataSource::JudicialBranch),
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
            deferToQueue: $deferToQueue
                || JudicialSyncRun::hasActiveBatch(JudicialSyncDataSource::Samai),
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

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Src\Application\AppUser\Process\DTOs\ProcessRegistrationRoutingDecision;
use Src\Application\Shared\Services\SamaiConsultService;
use Src\Domain\Process\Models\Process;

/**
 * Decide si el alta de un proceso SAMAI debe ejecutarse inline o ir a cola.
 *
 * A diferencia de la Rama Judicial (que pagina actuaciones), SAMAI devuelve todas
 * las actuaciones en una sola petición. La decisión se basa en el conteo total.
 *
 * Si el proceso ya existe en DB (de cualquier fuente), siempre es inline porque
 * no hay llamada a la API de SAMAI en el fast path.
 */
readonly class SamaiRegistrationSyncModeResolverService
{
    public function __construct(
        private SamaiConsultService $samaiService,
    ) {}

    public function handle(string $processNumber, string $organizationId): ProcessRegistrationRoutingDecision
    {
        $this->assertProcessNotAlreadyRegisteredForOrganization($processNumber, $organizationId);

        // Fast path: ya existe en DB → inline sin llamada a SAMAI
        if (Process::query()->whereProcessNumber($processNumber)->exists()) {
            return new ProcessRegistrationRoutingDecision(deferToQueue: false);
        }

        $this->samaiService->withSeed($processNumber);

        $searchResults = $this->samaiService->buscarProceso($processNumber);

        if ($searchResults === []) {
            abort(404, __('process.not_found_in_samai'));
        }

        $inlineMax = max(1, (int) config('samai.registration_inline_max_actuaciones', 50));

        // Verificar actuaciones por cada corporación nueva encontrada
        foreach ($searchResults as $result) {
            $corporacion = $this->samaiService->extractCorporacion($result, $processNumber);

            if ($corporacion === '') {
                continue;
            }

            // Solo contar si la instancia aún no existe en DB
            $alreadyInDb = Process::query()
                ->whereProcessNumber($processNumber)
                ->where('samai_corporacion', $corporacion)
                ->exists();

            if ($alreadyInDb) {
                continue;
            }

            $count = $this->samaiService->contarActuaciones($corporacion, $processNumber);

            if ($count > $inlineMax) {
                return new ProcessRegistrationRoutingDecision(deferToQueue: true);
            }
        }

        return new ProcessRegistrationRoutingDecision(deferToQueue: false);
    }

    private function assertProcessNotAlreadyRegisteredForOrganization(string $processNumber, string $organizationId): void
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

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Src\Application\AppUser\Process\DTOs\ProcessRegistrationRoutingDecision;
use Src\Application\Shared\Exceptions\ApiEmptyProcessesException;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Domain\Process\Models\Process;

/**
 * Decide si el alta por API debe ir en cola (muchas páginas de actuaciones) o ejecutarse inline para mejor UX.
 */
readonly class ProcessRegistrationSyncModeResolverService
{
    public function __construct(
        private JudicialBranchConsultService $judicialBranchConsultService,
    ) {}

    public function handle(string $processNumber, string $organizationId): ProcessRegistrationRoutingDecision
    {
        $this->assertProcessNotAlreadyRegisteredForOrganization($processNumber, $organizationId);

        $this->judicialBranchConsultService->withSeed($processNumber);

        $existingGlobally = Process::query()->whereProcessNumber($processNumber)->exists();
        if ($existingGlobally) {
            return new ProcessRegistrationRoutingDecision(deferToQueue: false);
        }

        try {
            $response = $this->judicialBranchConsultService->fetchProcesses($processNumber);
        } catch (ApiEmptyProcessesException) {
            abort(404, __('process.not_found_in_judicial_branch'));
        }

        if (! $response->isSuccessful || $response->data === []) {
            abort(404, __('process.not_found_in_judicial_branch'));
        }

        /** @var array<int, array<string, mixed>> $processesData */
        $processesData = $response->data;

        $inlineMaxPages = max(1, (int) config('judicial-branch.registration_inline_max_actuacion_pages', 2));

        $maxPagesAmongNewInstances = 1;

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

            $peek = $this->judicialBranchConsultService->peekActuacionesPagination($processId);

            if (! $peek->isSuccessful) {
                return new ProcessRegistrationRoutingDecision(deferToQueue: false, prefetchedApiProcesses: $processesData);
            }

            $maxPagesAmongNewInstances = max($maxPagesAmongNewInstances, $peek->totalPages);
        }

        if ($maxPagesAmongNewInstances > $inlineMaxPages) {
            return new ProcessRegistrationRoutingDecision(deferToQueue: true);
        }

        return new ProcessRegistrationRoutingDecision(deferToQueue: false, prefetchedApiProcesses: $processesData);
    }

    private function assertProcessNotAlreadyRegisteredForOrganization(string $processNumber, string $organizationId): void
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
}

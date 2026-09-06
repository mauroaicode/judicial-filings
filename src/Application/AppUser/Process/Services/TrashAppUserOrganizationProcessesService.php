<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Src\Application\Shared\Process\Services\TrashOrganizationProcessesService;
use Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Models\Process;

readonly class TrashAppUserOrganizationProcessesService
{
    public function __construct(
        private TrashOrganizationProcessesService $trashOrganizationProcessesService,
        private OrganizationProcessQuotaService $organizationProcessQuotaService,
    ) {}

    /**
     * Soft-trash org↔process links for the lawyer's organization.
     * Selecting any instance of a radicado trashes ALL instances of that radicado for the org
     * so the cupo is fully freed.
     *
     * @param  list<string>|null  $processIds
     * @return array{
     *     trashed_count: int,
     *     trashed_ids: list<string>,
     *     skipped: list<array{process_id: string, reason: string}>,
     *     quota: array{
     *         active_processes_count: int,
     *         max_active_processes: int|null,
     *         remaining_slots: int|null,
     *         is_unlimited: bool,
     *         is_at_limit: bool,
     *         can_add_process: bool
     *     }
     * }
     */
    public function handle(
        string $organizationId,
        ?array $processIds,
        bool $all,
        ?string $deletedBy = null,
    ): array {
        $idsToTrash = $all
            ? $this->resolveAllProcessIds($organizationId)
            : $this->expandToAllRadicadoInstances($organizationId, $processIds ?? []);

        $result = $this->trashOrganizationProcessesService->handle(
            $organizationId,
            $idsToTrash,
            $deletedBy,
            actorType: 'app_user',
        );

        $result['quota'] = $this->organizationProcessQuotaService->getSummary($organizationId);

        return $result;
    }

    /**
     * @return list<string>
     */
    private function resolveAllProcessIds(string $organizationId): array
    {
        return OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->pluck('process_id')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $processIds
     * @return list<string>
     */
    private function expandToAllRadicadoInstances(string $organizationId, array $processIds): array
    {
        if ($processIds === []) {
            return [];
        }

        $processNumbers = Process::query()
            ->whereIn('id', $processIds)
            ->pluck('process_number')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($processNumbers === []) {
            return array_values(array_unique($processIds));
        }

        return OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->whereHas('process', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($processNumbers): void {
                $query->whereIn('process_number', $processNumbers);
            })
            ->pluck('process_id')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Src\Application\AppUser\Process\Data\ProcessActionFilterData;
use Src\Domain\Process\Models\ProcessAction;

readonly class ProcessActionFinderService
{
    /**
     * Get paginated process actions with filters.
     *
     * @param  string|null  $processId  Optional process ID. If null, searches across all organization's processes.
     * @param  string|null  $organizationId  Required if processId is null to scope to organization.
     * @param  ProcessActionFilterData  $filters  The filtering criteria.
     * @param  int  $perPage  Number of items per page.
     */
    public function handle(?string $processId, ProcessActionFilterData $filters, int $perPage = 20, ?string $organizationId = null): LengthAwarePaginator
    {
        $query = ProcessAction::query()
            ->with(['alertHighlights', 'process']);

        if ($processId) {
            $query->whereProcess($processId);
        } elseif ($organizationId) {
            $query->whereHas('process.organizations', function (\Illuminate\Contracts\Database\Query\Builder $q) use ($organizationId): void {
                $q->where('organizations.id', $organizationId);
            });
        }

        return $query->filters($filters)
            ->orderedByRegistrationDate()
            ->orderByDesc('cons_action')
            ->paginate($perPage)
            ->appends(request()->query());
    }
}

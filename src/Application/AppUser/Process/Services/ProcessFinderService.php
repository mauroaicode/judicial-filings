<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Src\Application\AppUser\Process\Data\ProcessFilterData;
use Src\Domain\Process\Models\Process;

readonly class ProcessFinderService
{
    /**
     * Get paginated processes with relations and filters for an organization.
     *
     * @param  ProcessFilterData  $filters  The filtering criteria.
     * @param  string  $organizationId  The organization ID.
     * @param  int  $perPage  Number of items per page.
     */
    public function handle(ProcessFilterData $filters, string $organizationId, int $perPage = 20): LengthAwarePaginator
    {
        return Process::query()
            ->whereOrganization($organizationId)
            ->filters($filters)
            ->with(['actions', 'subjects', 'organizations' => function ($query) use ($organizationId): void {
                $query->where('organizations.id', $organizationId);
            }])
            ->orderedByProcessDate()
            ->paginate($perPage)
            ->appends(request()->query());
    }
}

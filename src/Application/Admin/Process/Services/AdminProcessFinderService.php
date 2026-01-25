<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Src\Application\Shared\Data\ProcessFilterData;
use Src\Domain\Process\Models\Process;

readonly class AdminProcessFinderService
{
    /**
     * Get paginated processes with relations and filters for all organizations.
     *
     * @param  ProcessFilterData  $filters  The filtering criteria.
     * @param  int  $perPage  Number of items per page.
     */
    public function handle(ProcessFilterData $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Process::query()
            ->filters($filters)
            ->with(['actions', 'subjects', 'organizations'])
            ->orderedByRegistrationDate()
            ->paginate($perPage)
            ->appends(request()->query());
    }
}

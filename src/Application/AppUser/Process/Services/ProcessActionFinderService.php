<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Src\Application\AppUser\Process\Data\ProcessActionFilterData;
use Src\Domain\Process\Models\ProcessAction;

readonly class ProcessActionFinderService
{
    /**
     * Get paginated process actions with filters for a process.
     *
     * @param  string  $processId  The process ID.
     * @param  ProcessActionFilterData  $filters  The filtering criteria.
     * @param  int  $perPage  Number of items per page.
     */
    public function handle(string $processId, ProcessActionFilterData $filters, int $perPage = 20): LengthAwarePaginator
    {
        return ProcessAction::query()
            ->whereProcess($processId)
            ->with('alertHighlights')
            ->filters($filters)
            ->orderedByConsActionDesc()
            ->paginate($perPage)
            ->appends(request()->query());
    }
}

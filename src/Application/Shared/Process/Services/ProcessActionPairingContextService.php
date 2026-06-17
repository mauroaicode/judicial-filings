<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Services;

use Illuminate\Support\Collection;
use Src\Domain\Process\Models\ProcessAction;

/**
 * Fetches a "pairing context" window of actions that fall just after the current page.
 * Passed to GroupProcessActionsService so fijación↔auto pairs that straddle a page
 * boundary can still be resolved.
 */
readonly class ProcessActionPairingContextService
{
    /**
     * @param  \Illuminate\Pagination\LengthAwarePaginator  $paginated  The already-paginated result for the current page.
     * @param  int  $buffer  How many extra items to fetch (default 10, matching cons_action proximity tolerance).
     * @return Collection<int, ProcessAction>
     */
    public function handle(\Illuminate\Pagination\LengthAwarePaginator $paginated, int $buffer = 10): Collection
    {
        if ($paginated->currentPage() >= $paginated->lastPage()) {
            return collect();
        }

        /** @var ProcessAction|null $first */
        $first = $paginated->getCollection()->first();

        if ($first === null) {
            return collect();
        }

        $offset = $paginated->currentPage() * $paginated->perPage();

        return ProcessAction::query()
            ->with(['alertHighlights', 'process'])
            ->whereProcess($first->process_id)
            ->orderedByActionDate()
            ->orderedByRegistrationDate()
            ->orderByDesc('cons_action')
            ->skip($offset)
            ->take($buffer)
            ->get();
    }
}

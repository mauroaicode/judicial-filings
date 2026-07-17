<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Timeline\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessTimelineEvent;

class ListProcessTimelineService
{
    /**
     * @return LengthAwarePaginator<int, ProcessTimelineEvent>
     */
    public function handle(
        string $processId,
        string $organizationId,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $this->ensureProcessBelongsToOrganization($processId, $organizationId);

        return ProcessTimelineEvent::query()
            ->forProcess($processId)
            ->visibleToOrganization($organizationId)
            ->latestFirst()
            ->paginate(min(max($perPage, 1), 100));
    }

    private function ensureProcessBelongsToOrganization(string $processId, string $organizationId): void
    {
        $exists = Process::query()
            ->whereKey($processId)
            ->whereOrganization($organizationId)
            ->exists();

        if (! $exists) {
            abort(404, __('process.not_found'));
        }
    }
}

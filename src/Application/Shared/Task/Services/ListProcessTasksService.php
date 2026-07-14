<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\Shared\Task\Data\ListProcessTasksFilterData;
use Src\Domain\Process\Models\Process;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Enums\TaskType;
use Src\Domain\Task\Models\Task;
use Src\Domain\Task\QueryBuilders\TaskQueryBuilder;

class ListProcessTasksService
{
    /**
     * List tasks for a process scoped to the given organization.
     */
    public function handle(
        string $processId,
        string $organizationId,
        ListProcessTasksFilterData $filters,
    ): LengthAwarePaginator {
        $this->ensureProcessBelongsToOrganization($processId, $organizationId);

        return $this->getTasks($processId, $organizationId, $filters);
    }

    private function ensureProcessBelongsToOrganization(string $processId, string $organizationId): void
    {
        $processExists = Process::query()
            ->whereKey($processId)
            ->whereOrganization($organizationId)
            ->exists();

        if (! $processExists) {
            abort(404, __('process.not_found'));
        }
    }

    private function getTasks(
        string $processId,
        string $organizationId,
        ListProcessTasksFilterData $filters,
    ): LengthAwarePaginator {
        return Task::query()
            ->with('process')
            ->whereOrganization($organizationId)
            ->whereProcess($processId)
            ->whereAppUser()
            ->when(
                $filters->status !== null && in_array($filters->status, TaskStatus::values(), true),
                fn (TaskQueryBuilder $q): TaskQueryBuilder => $q->whereStatus($filters->status),
            )
            ->when(
                $filters->type !== null && in_array($filters->type, TaskType::values(), true),
                fn (TaskQueryBuilder $q): TaskQueryBuilder => $q->whereType($filters->type),
            )
            ->orderedByCreatedAt()
            ->paginate($filters->per_page);
    }
}

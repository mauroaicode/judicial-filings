<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Models\Task;
use Src\Domain\Task\QueryBuilders\TaskQueryBuilder;

class ListTasksService
{
    /**
     * Get a paginated list of tasks based on filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        return $this->getTasks($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function getTasks(array $filters): LengthAwarePaginator
    {
        return Task::query()
            ->with('process')
            ->when(isset($filters['organization_id']), fn ($q): TaskQueryBuilder => $q->whereOrganization($filters['organization_id']))
            ->when(isset($filters['is_admin']), fn ($q): TaskQueryBuilder => $q->whereAdmin((bool) ($filters['is_admin'] ?? false)))
            ->when(isset($filters['process_id']), fn ($q): TaskQueryBuilder => $q->whereProcess($filters['process_id']))
            ->when(
                isset($filters['status']) && in_array($filters['status'], TaskStatus::values(), true),
                fn ($q): TaskQueryBuilder => $q->whereStatus($filters['status']),
                fn ($q): TaskQueryBuilder => $q->whereStatus(TaskStatus::PENDING),
            )
            ->orderedByCreatedAt()
            ->paginate((int) ($filters['per_page'] ?? 20));
    }
}

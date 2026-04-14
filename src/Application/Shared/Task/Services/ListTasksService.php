<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Domain\Task\Models\Task;

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
            ->when(isset($filters['organization_id']), fn ($q): \Src\Domain\Task\QueryBuilders\TaskQueryBuilder => $q->whereOrganization($filters['organization_id']))
            ->when(isset($filters['is_admin']), fn ($q): \Src\Domain\Task\QueryBuilders\TaskQueryBuilder => $q->whereAdmin((bool) ($filters['is_admin'] ?? false)))
            ->when(isset($filters['process_id']), fn ($q): \Src\Domain\Task\QueryBuilders\TaskQueryBuilder => $q->whereProcess($filters['process_id']))
            ->orderedByCreatedAt()
            ->paginate((int) ($filters['per_page'] ?? 20));
    }
}

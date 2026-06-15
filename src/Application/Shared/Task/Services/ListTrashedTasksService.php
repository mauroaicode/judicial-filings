<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Domain\Task\Models\Task;
use Src\Domain\Task\QueryBuilders\TaskQueryBuilder;

class ListTrashedTasksService
{
    /**
     * Get a paginated list of trashed tasks based on filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        return Task::query()
            ->onlyTrashed()
            ->with('process')
            ->when(isset($filters['organization_id']), fn ($q): TaskQueryBuilder => $q->whereOrganization($filters['organization_id']))
            ->when(isset($filters['is_admin']), fn ($q): TaskQueryBuilder => $q->whereAdmin((bool) ($filters['is_admin'] ?? false)))
            ->orderedByCreatedAt()
            ->paginate((int) ($filters['per_page'] ?? 20));
    }
}

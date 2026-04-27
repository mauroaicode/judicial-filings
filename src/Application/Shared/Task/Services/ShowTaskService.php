<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Src\Domain\Task\Models\Task;

class ShowTaskService
{
    /**
     * Get a specific task by its ID.
     */
    public function handle(string $id, ?string $organizationId = null): Task
    {
        return $this->findTask($id, $organizationId);
    }

    private function findTask(string $id, ?string $organizationId = null): Task
    {
        return Task::query()
            ->with('process')
            ->when($organizationId, fn ($q): \Src\Domain\Task\QueryBuilders\TaskQueryBuilder => $q->whereOrganization($organizationId))
            ->findOrFail($id);
    }
}

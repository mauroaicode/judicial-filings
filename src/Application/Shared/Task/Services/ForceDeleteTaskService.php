<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Src\Domain\Task\Models\Task;

class ForceDeleteTaskService
{
    /**
     * Permanently delete a trashed task.
     */
    public function handle(string $id, ?string $organizationId = null): void
    {
        $task = $this->findTrashedTask($id, $organizationId);

        $task->forceDelete();
    }

    private function findTrashedTask(string $id, ?string $organizationId = null): Task
    {
        return Task::query()
            ->onlyTrashed()
            ->when($organizationId, fn ($q): \Src\Domain\Task\QueryBuilders\TaskQueryBuilder => $q->whereOrganization($organizationId))
            ->findOrFail($id);
    }
}

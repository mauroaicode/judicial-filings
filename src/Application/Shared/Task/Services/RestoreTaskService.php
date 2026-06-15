<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Src\Domain\Task\Models\Task;

class RestoreTaskService
{
    /**
     * Restore a trashed task.
     */
    public function handle(string $id, ?string $organizationId = null): Task
    {
        $task = $this->findTrashedTask($id, $organizationId);

        $task->restore();

        return $task->fresh()->load('process');
    }

    private function findTrashedTask(string $id, ?string $organizationId = null): Task
    {
        return Task::query()
            ->onlyTrashed()
            ->when($organizationId, fn ($q): \Src\Domain\Task\QueryBuilders\TaskQueryBuilder => $q->whereOrganization($organizationId))
            ->findOrFail($id);
    }
}

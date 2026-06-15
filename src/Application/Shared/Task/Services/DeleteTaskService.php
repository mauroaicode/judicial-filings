<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Src\Domain\Task\Models\Task;

class DeleteTaskService
{
    /**
     * Move a task to the trash (soft delete).
     */
    public function handle(string $id, ?string $organizationId = null): void
    {
        $task = $this->findTask($id, $organizationId);

        $this->deleteTask($task);
    }

    private function findTask(string $id, ?string $organizationId = null): Task
    {
        return Task::query()
            ->when($organizationId, fn ($q): \Src\Domain\Task\QueryBuilders\TaskQueryBuilder => $q->whereOrganization($organizationId))
            ->findOrFail($id);
    }

    private function deleteTask(Task $task): void
    {
        $task->delete();
    }
}

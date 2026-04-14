<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Src\Domain\Task\Models\Task;

class DeleteTaskService
{
    /**
     * Delete a task by its ID.
     */
    public function handle(string $id): void
    {
        $task = $this->findTask($id);

        $this->deleteTask($task);
    }

    private function findTask(string $id): Task
    {
        return Task::query()->findOrFail($id);
    }

    private function deleteTask(Task $task): void
    {
        $task->delete();
    }
}

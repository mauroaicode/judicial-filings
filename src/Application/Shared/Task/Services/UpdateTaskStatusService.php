<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Models\Task;

class UpdateTaskStatusService
{
    /**
     * Update the status of a task.
     */
    public function handle(string $id, TaskStatus $status, ?string $organizationId = null): Task
    {
        $task = $this->findTask($id, $organizationId);

        if ($task->status !== $status) {
            $task->update(['status' => $status]);
        }

        return $task->fresh()->load('process');
    }

    private function findTask(string $id, ?string $organizationId = null): Task
    {
        return Task::query()
            ->when($organizationId, fn ($q): \Src\Domain\Task\QueryBuilders\TaskQueryBuilder => $q->whereOrganization($organizationId))
            ->findOrFail($id);
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Src\Domain\Task\Enums\TaskStatus;

class CompleteTaskService
{
    public function __construct(
        private readonly UpdateTaskStatusService $updateTaskStatusService,
    ) {}

    /**
     * Mark a task as completed (archived).
     */
    public function handle(string $id, ?string $organizationId = null): \Src\Domain\Task\Models\Task
    {
        return $this->updateTaskStatusService->handle($id, TaskStatus::COMPLETED, $organizationId);
    }
}

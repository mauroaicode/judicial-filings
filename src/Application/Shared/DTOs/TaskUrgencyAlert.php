<?php

declare(strict_types=1);

namespace Src\Application\Shared\DTOs;

use Src\Domain\Task\Enums\TaskUrgencyLevel;
use Src\Domain\Task\Models\Task;

readonly class TaskUrgencyAlert
{
    public function __construct(
        public Task $task,
        public TaskUrgencyLevel $urgencyLevel,
        public int $daysElapsed,
        public string $taskUrl,
    ) {}

    public function organizationId(): string
    {
        return $this->task->organization_id;
    }

    public function processNumber(): ?string
    {
        return $this->task->process?->process_number;
    }

    public function notificationType(): string
    {
        return $this->urgencyLevel->notificationType();
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\DTOs;

use Src\Domain\Task\Models\Task;

readonly class TaskDueDateReminderAlert
{
    public function __construct(
        public Task $task,
        public int $daysRemaining,
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
        return 'task_due_date_reminder';
    }

    public function severityColor(): string
    {
        return (string) $this->daysRemaining;
    }
}

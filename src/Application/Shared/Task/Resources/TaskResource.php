<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\Task\Models\Task;

class TaskResource extends Resource
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public ?string $due_date,
        public ?int $reminder_days,
        public string $status,
        public string $status_label,
        public bool $is_admin,
        public ?string $process_id,
        public ?string $process_number,
        public string $organization_id,
        public ?string $created_at,
        public ?string $deleted_at,
    ) {}

    public static function fromModel(Task $task): self
    {
        return new self(
            id: $task->id,
            title: $task->title,
            description: $task->description,
            due_date: $task->due_date?->toDateString(),
            reminder_days: $task->reminder_days,
            status: $task->status->value,
            status_label: $task->status->getLabel(),
            is_admin: $task->is_admin,
            process_id: $task->process_id,
            process_number: $task->process?->process_number,
            organization_id: $task->organization_id,
            created_at: $task->created_at?->toISOString(),
            deleted_at: $task->deleted_at?->toISOString(),
        );
    }
}

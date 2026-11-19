<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Pdf;

use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\TaskUrgencyHelper;
use Src\Domain\Process\Models\Process;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Models\Task;

class CollectProcessPdfTasksService
{
    /**
     * App-user tasks of this process, pending first, then by due date.
     *
     * @return list<array<string, mixed>>
     */
    public function handle(Process $process, string $organizationId): array
    {
        return Task::query()
            ->whereOrganization($organizationId)
            ->whereProcess($process->id)
            ->whereAppUser()
            ->get()
            ->sortBy(function (Task $task): string {
                $statusOrder = match ($task->status) {
                    TaskStatus::PENDING => 0,
                    TaskStatus::DRAFT => 1,
                    default => 2,
                };

                $due = $task->due_date?->format('Y-m-d H:i:s') ?? '9999-12-31 23:59:59';

                return $statusOrder.'-'.$due;
            })
            ->values()
            ->map(function (Task $task): array {
                $urgency = TaskUrgencyHelper::resolveDisplayLevel($task);

                return [
                    'title' => $task->title,
                    'description' => $task->description,
                    'type_label' => $task->type->getLabel(),
                    'status_label' => $task->status->getLabel(),
                    'status' => $task->status->value,
                    'due_date' => $task->due_date
                        ? DateFormatHelper::formatDateTimeWithDayOfWeek($task->due_date)
                        : null,
                    'reminder_days' => $task->reminder_days,
                    'urgency_label' => $urgency?->getLabel(),
                    'days_overdue' => TaskUrgencyHelper::daysOverdue($task->due_date),
                    'pauses_process' => $task->type->pausesProcess(),
                ];
            })
            ->all();
    }
}

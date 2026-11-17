<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

use Carbon\CarbonInterface;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Models\Task;

final class TaskDueDateReminderHelper
{
    /**
     * Business days remaining until due date (0 = due today, negative = overdue).
     * Weekends and Colombian public holidays do not count.
     */
    public static function daysUntilDue(CarbonInterface $dueDate): int
    {
        return BusinessDaysHelper::daysUntil($dueDate);
    }

    /**
     * Returns business days remaining when a due-date reminder should be sent, or null otherwise.
     *
     * Notifies daily while: 0 <= business_days_remaining <= reminder_days (configured by user).
     * `reminder_days` is interpreted as business days.
     */
    public static function resolveNotifiableDaysRemaining(Task $task): ?int
    {
        if ($task->status !== TaskStatus::PENDING) {
            return null;
        }

        if ($task->due_date === null) {
            return null;
        }

        $daysRemaining = self::daysUntilDue($task->due_date);

        if ($daysRemaining < 0) {
            return null;
        }

        if ($daysRemaining > $task->reminder_days) {
            return null;
        }

        if ($task->last_due_reminder_sent_on !== null && $task->last_due_reminder_sent_on->isSameDay(today())) {
            return null;
        }

        return $daysRemaining;
    }

    public static function buildTaskFrontendUrl(string $taskId): string
    {
        return TaskUrgencyHelper::buildTaskFrontendUrl($taskId);
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

use Carbon\CarbonInterface;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Enums\TaskUrgencyLevel;
use Src\Domain\Task\Models\Task;

final class TaskUrgencyHelper
{
    public static function alert1ThresholdDays(): int
    {
        return (int) config('tasks.urgency_thresholds.alert_1', 10);
    }

    public static function alert2ThresholdDays(): int
    {
        return (int) config('tasks.urgency_thresholds.alert_2', 15);
    }

    public static function criticalThresholdDays(): int
    {
        return (int) config('tasks.urgency_thresholds.critical', 30);
    }

    /**
     * Days past the due date (0 = due today or not yet due, positive = overdue).
     */
    public static function daysOverdue(?CarbonInterface $dueDate): int
    {
        if (! $dueDate instanceof \Carbon\CarbonInterface) {
            return 0;
        }

        $daysUntilDue = TaskDueDateReminderHelper::daysUntilDue($dueDate);

        return max(0, -$daysUntilDue);
    }

    /**
     * Resolve urgency level from days past due date.
     *
     * Day 0 (due today) and overdue &lt; 10 → normal (green).
     * ≥ 10 → alert_1, ≥ 15 → alert_2, ≥ 30 → critical.
     */
    public static function fromDaysOverdue(int $daysOverdue): TaskUrgencyLevel
    {
        return match (true) {
            $daysOverdue >= self::criticalThresholdDays() => TaskUrgencyLevel::CRITICAL,
            $daysOverdue >= self::alert2ThresholdDays() => TaskUrgencyLevel::ALERT_2,
            $daysOverdue >= self::alert1ThresholdDays() => TaskUrgencyLevel::ALERT_1,
            default => TaskUrgencyLevel::NORMAL,
        };
    }

    /**
     * Resolve display urgency level for agenda cards (all pending tasks with a due date).
     */
    public static function resolveDisplayLevel(Task $task): ?TaskUrgencyLevel
    {
        if ($task->status !== TaskStatus::PENDING) {
            return null;
        }

        if ($task->due_date === null) {
            return null;
        }

        return self::fromDaysOverdue(self::daysOverdue($task->due_date));
    }

    /**
     * Returns the urgency level that should trigger a notification, or null if none.
     *
     * Pre-due and due day are handled by due-date reminders. Urgency alerts start
     * at 10+ days past due.
     */
    public static function resolveNotifiableLevel(Task $task): ?TaskUrgencyLevel
    {
        if ($task->status !== TaskStatus::PENDING) {
            return null;
        }

        if ($task->due_date === null) {
            return null;
        }

        // Before / on due date: only due-date reminders apply.
        if (TaskDueDateReminderHelper::daysUntilDue($task->due_date) >= 0) {
            return null;
        }

        $current = self::fromDaysOverdue(self::daysOverdue($task->due_date));

        if (! $current->isNotifiable()) {
            return null;
        }

        $lastNotified = $task->last_notified_urgency_level !== null
            ? TaskUrgencyLevel::tryFrom($task->last_notified_urgency_level)
            : null;

        if ($lastNotified instanceof TaskUrgencyLevel && $current->rank() <= $lastNotified->rank()) {
            return null;
        }

        return $current;
    }

    public static function buildTaskFrontendUrl(string $taskId): string
    {
        $base = rtrim((string) config('tasks.frontend.base_url', 'http://localhost:4200'), '/');
        $path = rtrim((string) config('tasks.frontend.tasks_path', '/tareas'), '/');

        return "{$base}{$path}/{$taskId}";
    }
}

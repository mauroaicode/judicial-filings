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
     * Days elapsed since creation (start of day comparison).
     */
    public static function daysElapsed(CarbonInterface $createdAt): int
    {
        return (int) $createdAt->copy()->startOfDay()->diffInDays(today()->startOfDay());
    }

    /**
     * Resolve urgency level from creation date.
     */
    public static function fromCreatedAt(CarbonInterface $createdAt): TaskUrgencyLevel
    {
        $days = self::daysElapsed($createdAt);

        return match (true) {
            $days >= self::criticalThresholdDays() => TaskUrgencyLevel::CRITICAL,
            $days >= self::alert2ThresholdDays() => TaskUrgencyLevel::ALERT_2,
            $days >= self::alert1ThresholdDays() => TaskUrgencyLevel::ALERT_1,
            default => TaskUrgencyLevel::NORMAL,
        };
    }

    /**
     * Returns the urgency level that should trigger a notification, or null if none.
     */
    public static function resolveNotifiableLevel(Task $task): ?TaskUrgencyLevel
    {
        if ($task->status !== TaskStatus::PENDING) {
            return null;
        }

        // After due date: overdue reminders stop; urgency alerts take over.
        if ($task->due_date !== null && TaskDueDateReminderHelper::daysUntilDue($task->due_date) >= 0) {
            return null;
        }

        $current = self::fromCreatedAt($task->created_at);

        $isOverdue = $task->due_date !== null
            && TaskDueDateReminderHelper::daysUntilDue($task->due_date) < 0;

        // Once past due date, notify at least at alert_1 even if creation age is still below 10 days.
        if ($isOverdue && $current === TaskUrgencyLevel::NORMAL) {
            $current = TaskUrgencyLevel::ALERT_1;
        }

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

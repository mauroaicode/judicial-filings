<?php

declare(strict_types=1);

namespace Tests\Application\Shared\Helpers;

use Illuminate\Support\Carbon;
use Src\Application\Shared\Helpers\TaskDueDateReminderHelper;
use Src\Application\Shared\Helpers\TaskUrgencyHelper;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Enums\TaskUrgencyLevel;
use Src\Domain\Task\Models\Task;

beforeEach(function (): void {
    Carbon::setTestNow('2026-06-14 08:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('calculates days until due date', function (): void {
    $dueDate = Carbon::parse('2026-06-17');

    expect(TaskDueDateReminderHelper::daysUntilDue($dueDate))->toBe(3);
});

it('notifies when days remaining is within reminder_days window', function (): void {
    $task = Task::factory()->make([
        'status' => TaskStatus::PENDING,
        'due_date' => Carbon::parse('2026-06-17'),
        'reminder_days' => 3,
        'last_due_reminder_sent_on' => null,
    ]);

    expect(TaskDueDateReminderHelper::resolveNotifiableDaysRemaining($task))->toBe(3);
});

it('does not notify when days remaining exceeds reminder_days', function (): void {
    $task = Task::factory()->make([
        'status' => TaskStatus::PENDING,
        'due_date' => Carbon::parse('2026-06-20'),
        'reminder_days' => 3,
    ]);

    expect(TaskDueDateReminderHelper::resolveNotifiableDaysRemaining($task))->toBeNull();
});

it('does not notify twice on the same day', function (): void {
    $task = Task::factory()->make([
        'status' => TaskStatus::PENDING,
        'due_date' => Carbon::parse('2026-06-17'),
        'reminder_days' => 3,
        'last_due_reminder_sent_on' => Carbon::parse('2026-06-14'),
    ]);

    expect(TaskDueDateReminderHelper::resolveNotifiableDaysRemaining($task))->toBeNull();
});

it('notifies daily until due date including due day', function (): void {
    $task = Task::factory()->make([
        'status' => TaskStatus::PENDING,
        'due_date' => Carbon::parse('2026-06-14'),
        'reminder_days' => 5,
    ]);

    expect(TaskDueDateReminderHelper::resolveNotifiableDaysRemaining($task))->toBe(0);
});

it('does not send due reminders after due date', function (): void {
    $task = Task::factory()->make([
        'status' => TaskStatus::PENDING,
        'due_date' => Carbon::parse('2026-06-10'),
        'reminder_days' => 3,
    ]);

    expect(TaskDueDateReminderHelper::resolveNotifiableDaysRemaining($task))->toBeNull();
});

it('blocks urgency alerts while task is before due date', function (): void {
    $task = Task::factory()->make([
        'status' => TaskStatus::PENDING,
        'due_date' => Carbon::parse('2026-06-20'),
        'reminder_days' => 3,
    ]);

    expect(TaskUrgencyHelper::resolveNotifiableLevel($task))->toBeNull();
});

it('does not send urgency alerts in the first 9 days past due', function (): void {
    $task = Task::factory()->make([
        'status' => TaskStatus::PENDING,
        'due_date' => Carbon::parse('2026-06-10'),
        'reminder_days' => 3,
        'last_notified_urgency_level' => null,
    ]);

    expect(TaskUrgencyHelper::daysOverdue($task->due_date))->toBe(4);
    expect(TaskUrgencyHelper::resolveDisplayLevel($task))->toBe(TaskUrgencyLevel::NORMAL);
    expect(TaskUrgencyHelper::resolveNotifiableLevel($task))->toBeNull();
});

it('sends urgency alerts starting at 10 days past due', function (): void {
    $task = Task::factory()->make([
        'status' => TaskStatus::PENDING,
        'due_date' => Carbon::parse('2026-06-04'),
        'reminder_days' => 5,
        'last_notified_urgency_level' => null,
    ]);

    expect(TaskUrgencyHelper::daysOverdue($task->due_date))->toBe(10);
    expect(TaskUrgencyHelper::resolveNotifiableLevel($task))->toBe(TaskUrgencyLevel::ALERT_1);
});

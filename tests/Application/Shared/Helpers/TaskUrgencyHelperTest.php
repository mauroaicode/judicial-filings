<?php

declare(strict_types=1);

namespace Tests\Application\Shared\Helpers;

use Illuminate\Support\Carbon;
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

it('resolves normal urgency before and on due date', function (): void {
    $beforeDue = Task::factory()->make([
        'status' => TaskStatus::PENDING,
        'due_date' => Carbon::parse('2026-06-20'),
    ]);

    $dueToday = Task::factory()->make([
        'status' => TaskStatus::PENDING,
        'due_date' => Carbon::parse('2026-06-14'),
    ]);

    expect(TaskUrgencyHelper::daysOverdue($beforeDue->due_date))->toBe(0);
    expect(TaskUrgencyHelper::resolveDisplayLevel($beforeDue))->toBe(TaskUrgencyLevel::NORMAL);
    expect(TaskUrgencyHelper::resolveNotifiableLevel($beforeDue))->toBeNull();

    expect(TaskUrgencyHelper::daysOverdue($dueToday->due_date))->toBe(0);
    expect(TaskUrgencyHelper::resolveDisplayLevel($dueToday))->toBe(TaskUrgencyLevel::NORMAL);
    expect(TaskUrgencyHelper::resolveNotifiableLevel($dueToday))->toBeNull();
});

it('resolves normal urgency when overdue less than 10 days', function (): void {
    $task = Task::factory()->make([
        'status' => TaskStatus::PENDING,
        'due_date' => Carbon::parse('2026-06-05'),
    ]);

    expect(TaskUrgencyHelper::daysOverdue($task->due_date))->toBe(9);
    expect(TaskUrgencyHelper::resolveDisplayLevel($task))->toBe(TaskUrgencyLevel::NORMAL);
    expect(TaskUrgencyHelper::resolveNotifiableLevel($task))->toBeNull();
});

it('resolves alert levels by days past due date', function (): void {
    expect(TaskUrgencyHelper::fromDaysOverdue(10))->toBe(TaskUrgencyLevel::ALERT_1);
    expect(TaskUrgencyHelper::fromDaysOverdue(15))->toBe(TaskUrgencyLevel::ALERT_2);
    expect(TaskUrgencyHelper::fromDaysOverdue(30))->toBe(TaskUrgencyLevel::CRITICAL);
});

it('notifies alert_1 at 10 days past due', function (): void {
    $task = Task::factory()->make([
        'status' => TaskStatus::PENDING,
        'due_date' => Carbon::parse('2026-06-04'),
        'last_notified_urgency_level' => null,
    ]);

    expect(TaskUrgencyHelper::daysOverdue($task->due_date))->toBe(10);
    expect(TaskUrgencyHelper::resolveNotifiableLevel($task))->toBe(TaskUrgencyLevel::ALERT_1);
});

it('returns null when the same urgency level was already notified', function (): void {
    $task = Task::factory()->make([
        'status' => TaskStatus::PENDING,
        'due_date' => Carbon::parse('2026-06-02'),
        'last_notified_urgency_level' => TaskUrgencyLevel::ALERT_1->value,
    ]);

    expect(TaskUrgencyHelper::daysOverdue($task->due_date))->toBe(12);
    expect(TaskUrgencyHelper::resolveNotifiableLevel($task))->toBeNull();
});

it('returns the next urgency level when escalation occurs', function (): void {
    $task = Task::factory()->make([
        'status' => TaskStatus::PENDING,
        'due_date' => Carbon::parse('2026-05-29'),
        'last_notified_urgency_level' => TaskUrgencyLevel::ALERT_1->value,
    ]);

    expect(TaskUrgencyHelper::daysOverdue($task->due_date))->toBe(16);
    expect(TaskUrgencyHelper::resolveNotifiableLevel($task))->toBe(TaskUrgencyLevel::ALERT_2);
});

it('skips non pending tasks', function (): void {
    $task = Task::factory()->completed()->make([
        'due_date' => Carbon::parse('2026-05-01'),
    ]);

    expect(TaskUrgencyHelper::resolveDisplayLevel($task))->toBeNull();
    expect(TaskUrgencyHelper::resolveNotifiableLevel($task))->toBeNull();
});

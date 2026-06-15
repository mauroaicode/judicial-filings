<?php

declare(strict_types=1);

namespace Tests\Application\Shared\Helpers;

use Illuminate\Support\Carbon;
use Src\Application\Shared\Helpers\TaskUrgencyHelper;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Enums\TaskUrgencyLevel;
use Src\Domain\Task\Models\Task;

it('resolves normal urgency for tasks younger than 10 days', function (): void {
    $task = Task::factory()->make([
        'created_at' => Carbon::now()->subDays(5),
        'status' => TaskStatus::PENDING,
    ]);

    expect(TaskUrgencyHelper::fromCreatedAt($task->created_at))->toBe(TaskUrgencyLevel::NORMAL);
    expect(TaskUrgencyHelper::resolveNotifiableLevel($task))->toBeNull();
});

it('resolves alert levels by days elapsed', function (): void {
    expect(TaskUrgencyHelper::fromCreatedAt(Carbon::now()->subDays(10))->value)->toBe('alert_1');
    expect(TaskUrgencyHelper::fromCreatedAt(Carbon::now()->subDays(15))->value)->toBe('alert_2');
    expect(TaskUrgencyHelper::fromCreatedAt(Carbon::now()->subDays(30))->value)->toBe('critical');
});

it('returns null when the same urgency level was already notified', function (): void {
    $task = Task::factory()->make([
        'created_at' => Carbon::now()->subDays(12),
        'status' => TaskStatus::PENDING,
        'last_notified_urgency_level' => TaskUrgencyLevel::ALERT_1->value,
    ]);

    expect(TaskUrgencyHelper::resolveNotifiableLevel($task))->toBeNull();
});

it('returns the next urgency level when escalation occurs', function (): void {
    $task = Task::factory()->make([
        'created_at' => Carbon::now()->subDays(16),
        'due_date' => Carbon::now()->subDay(),
        'status' => TaskStatus::PENDING,
        'last_notified_urgency_level' => TaskUrgencyLevel::ALERT_1->value,
    ]);

    expect(TaskUrgencyHelper::resolveNotifiableLevel($task))->toBe(TaskUrgencyLevel::ALERT_2);
});

it('skips non pending tasks', function (): void {
    $task = Task::factory()->completed()->make([
        'created_at' => Carbon::now()->subDays(20),
    ]);

    expect(TaskUrgencyHelper::resolveNotifiableLevel($task))->toBeNull();
});

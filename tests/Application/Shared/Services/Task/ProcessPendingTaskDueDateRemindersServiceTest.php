<?php

declare(strict_types=1);

namespace Tests\Application\Shared\Services\Task;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Src\Application\Shared\Notifications\TaskDueDateReminderInternalNotification;
use Src\Application\Shared\Notifications\TaskDueDateReminderMailNotification;
use Src\Application\Shared\Services\Task\ProcessPendingTaskDueDateRemindersService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Task\Models\Task;

beforeEach(function (): void {
    Carbon::setTestNow('2026-06-14 08:00:00');
    Notification::fake();

    $this->organization = Organization::factory()->create([
        'email' => 'org-alerts@example.com',
    ]);
    $this->process = Process::factory()->create();
    $this->organization->processes()->attach($this->process->id, [
        'is_active' => true,
        'interest_date' => now(),
    ]);

    $this->appUser = AppUser::factory()->create();
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);

    OrganizationNotificationChannel::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'internal',
        'channel_value' => 'internal',
        'is_active' => true,
        'priority' => 1,
    ]);

    OrganizationNotificationChannel::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'email',
        'channel_value' => 'org-alerts@example.com',
        'is_active' => true,
        'priority' => 2,
    ]);

    $this->service = app(ProcessPendingTaskDueDateRemindersService::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('sends daily due-date countdown when within reminder_days', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'due_date' => '2026-06-17',
        'reminder_days' => 3,
    ]);

    $firstRun = $this->service->handle($this->organization->id);
    $secondRun = $this->service->handle($this->organization->id);

    expect($firstRun['notified'])->toBe(1);
    expect($secondRun['notified'])->toBe(0);

    $task->refresh();
    expect($task->last_due_reminder_sent_on?->toDateString())->toBe('2026-06-14');

    Notification::assertSentTo($this->appUser, TaskDueDateReminderInternalNotification::class);
    Notification::assertSentOnDemand(TaskDueDateReminderMailNotification::class);

    $notification = OrganizationNotification::query()
        ->where('notifiable_id', $task->id)
        ->where('notification_type', 'task_due_date_reminder')
        ->where('severity_color', '3')
        ->first();

    expect($notification)->not->toBeNull();
});

it('sends again on the next day with updated days remaining', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
        'due_date' => '2026-06-16',
        'reminder_days' => 3,
        'last_due_reminder_sent_on' => '2026-06-13',
    ]);

    OrganizationNotification::query()->create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $task->id,
        'notifiable_type' => $task->getMorphClass(),
        'notification_type' => 'task_due_date_reminder',
        'severity_color' => '3',
        'is_notified' => true,
        'is_viewed' => true,
        'notified_at' => now()->subDay(),
    ]);

    Carbon::setTestNow('2026-06-14 08:00:00');

    $result = $this->service->handle($this->organization->id);

    expect($result['notified'])->toBe(1);
    expect($task->fresh()->last_due_reminder_sent_on?->toDateString())->toBe('2026-06-14');

    $notification = OrganizationNotification::query()
        ->where('notifiable_id', $task->id)
        ->where('notification_type', 'task_due_date_reminder')
        ->first();

    expect($notification)->not->toBeNull();
    expect($notification->severity_color)->toBe('2');
    expect($notification->is_viewed)->toBeFalse();
});

it('does not fail when organization notification already exists for the same task', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
        'due_date' => '2026-06-14',
        'reminder_days' => 0,
        'last_due_reminder_sent_on' => null,
    ]);

    OrganizationNotification::query()->create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $task->id,
        'notifiable_type' => $task->getMorphClass(),
        'notification_type' => 'task_due_date_reminder',
        'severity_color' => '0',
        'is_notified' => true,
        'is_viewed' => true,
        'notified_at' => now(),
    ]);

    $result = $this->service->handle($this->organization->id);

    expect($result['notified'])->toBe(1);
    expect(OrganizationNotification::query()->where('notifiable_id', $task->id)->count())->toBe(1);
});

it('does not send due reminders after due date', function (): void {
    Task::factory()->create([
        'organization_id' => $this->organization->id,
        'due_date' => '2026-06-10',
        'reminder_days' => 3,
    ]);

    $result = $this->service->handle($this->organization->id);

    expect($result['notified'])->toBe(0);
    Notification::assertNothingSent();
});

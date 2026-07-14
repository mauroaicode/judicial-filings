<?php

declare(strict_types=1);

namespace Tests\Application\Shared\Services\Task;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Src\Application\Shared\Notifications\TaskUrgencyInternalNotification;
use Src\Application\Shared\Notifications\TaskUrgencyMailNotification;
use Src\Application\Shared\Services\Task\ProcessPendingTaskUrgencyAlertsService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Task\Enums\TaskUrgencyLevel;
use Src\Domain\Task\Models\Task;

beforeEach(function (): void {
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

    $this->service = app(ProcessPendingTaskUrgencyAlertsService::class);
});

it('sends alert_1 notification once for a task 10 days past due', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'due_date' => Carbon::now()->subDays(10)->toDateString(),
        'reminder_days' => 3,
    ]);

    $firstRun = $this->service->handle($this->organization->id);
    $secondRun = $this->service->handle($this->organization->id);

    expect($firstRun['notified'])->toBe(1);
    expect($secondRun['notified'])->toBe(0);

    $task->refresh();
    expect($task->last_notified_urgency_level)->toBe(TaskUrgencyLevel::ALERT_1->value);

    $orgNotification = OrganizationNotification::query()
        ->where('organization_id', $this->organization->id)
        ->where('notifiable_id', $task->id)
        ->where('notification_type', 'task_urgency_alert_1')
        ->first();

    expect($orgNotification)->not->toBeNull();
    expect($orgNotification->is_notified)->toBeTrue();

    Notification::assertSentTo($this->appUser, TaskUrgencyInternalNotification::class);
    Notification::assertSentOnDemand(TaskUrgencyMailNotification::class);
});

it('sends alert_2 when task escalates after alert_1 was notified', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'due_date' => Carbon::now()->subDays(15)->toDateString(),
        'reminder_days' => 3,
        'last_notified_urgency_level' => TaskUrgencyLevel::ALERT_1->value,
    ]);

    $result = $this->service->handle($this->organization->id);

    expect($result['notified'])->toBe(1);
    expect($task->fresh()->last_notified_urgency_level)->toBe(TaskUrgencyLevel::ALERT_2->value);

    Notification::assertSentTo($this->appUser, TaskUrgencyInternalNotification::class);
});

it('does not fail when organization notification already exists for retesting', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'due_date' => Carbon::now()->subDays(10)->toDateString(),
        'reminder_days' => 3,
        'last_notified_urgency_level' => null,
    ]);

    OrganizationNotification::query()->create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $task->id,
        'notifiable_type' => $task->getMorphClass(),
        'notification_type' => 'task_urgency_alert_1',
        'severity_color' => TaskUrgencyLevel::ALERT_1->value,
        'is_notified' => true,
        'is_viewed' => true,
        'notified_at' => now(),
    ]);

    $result = $this->service->handle($this->organization->id);

    expect($result['notified'])->toBe(1);
    expect(OrganizationNotification::query()->where('notifiable_id', $task->id)->where('notification_type', 'task_urgency_alert_1')->count())->toBe(1);
});

it('does not send urgency alerts before due date', function (): void {
    Task::factory()->create([
        'organization_id' => $this->organization->id,
        'due_date' => Carbon::now()->addDays(5)->toDateString(),
        'reminder_days' => 3,
    ]);

    $result = $this->service->handle($this->organization->id);

    expect($result['notified'])->toBe(0);
    Notification::assertNothingSent();
});

it('does not notify completed or trashed tasks', function (): void {
    $completed = Task::factory()->completed()->create([
        'organization_id' => $this->organization->id,
        'due_date' => Carbon::now()->subDays(20)->toDateString(),
    ]);

    $trashed = Task::factory()->create([
        'organization_id' => $this->organization->id,
        'due_date' => Carbon::now()->subDays(20)->toDateString(),
    ]);
    $trashed->delete();

    $result = $this->service->handle($this->organization->id);

    expect($result['notified'])->toBe(0);

    Notification::assertNothingSent();
    Notification::assertNothingSentTo($this->appUser);

    expect(OrganizationNotification::query()->where('notifiable_id', $completed->id)->exists())->toBeFalse();
});

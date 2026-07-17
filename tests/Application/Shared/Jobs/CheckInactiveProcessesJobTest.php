<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Src\Application\Shared\Jobs\CheckInactiveProcessesJob;
use Src\Application\Shared\Process\Timeline\Services\RecordSemaphoreTimelineEventService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessTimelineEvent;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create([
        'name' => 'Job Org '.Str::uuid(),
    ]);
    $this->appUser = AppUser::factory()->create();
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);
});

it('sets inactivity_alert_level to red and creates notification for demandante process inactive > 90 days', function (): void {
    $process = Process::factory()->create([
        'last_activity_date' => Carbon::now()->subDays(91)->toDateString(),
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'lawyer_role' => ProcessLawyerRole::PLAINTIFF->value,
    ]);

    $job = new CheckInactiveProcessesJob;
    $job->handle(app(RecordSemaphoreTimelineEventService::class));

    // Assert pivot was updated
    $pivot = $process->organizations()->first()->pivot;
    expect($pivot->inactivity_alert_level)->toBe('red');

    // Assert organization notification was created
    $notification = OrganizationNotification::query()
        ->where('organization_id', $this->organization->id)
        ->where('notifiable_id', $process->id)
        ->where('notification_type', 'inactividad_roja')
        ->first();

    expect($notification)->not->toBeNull();

    $event = ProcessTimelineEvent::query()
        ->where('process_id', $process->id)
        ->where('organization_id', $this->organization->id)
        ->where('event_type', ProcessTimelineEventType::SEMAPHORE_CHANGED->value)
        ->first();

    expect($event?->payload)->toMatchArray(['from' => null, 'to' => 'red']);
});

it('sets inactivity_alert_level to green and creates green notification for demandado process inactive > 90 days', function (): void {
    $process = Process::factory()->create([
        'last_activity_date' => Carbon::now()->subDays(91)->toDateString(),
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'lawyer_role' => ProcessLawyerRole::DEFENDANT->value,
    ]);

    $job = new CheckInactiveProcessesJob;
    $job->handle(app(RecordSemaphoreTimelineEventService::class));

    $pivot = $process->organizations()->first()->pivot;
    expect($pivot->inactivity_alert_level)->toBe('green');

    $notification = OrganizationNotification::query()
        ->where('organization_id', $this->organization->id)
        ->where('notifiable_id', $process->id)
        ->where('notification_type', 'inactividad_verde')
        ->first();

    expect($notification)->not->toBeNull();

    $event = ProcessTimelineEvent::query()
        ->where('process_id', $process->id)
        ->where('event_type', ProcessTimelineEventType::SEMAPHORE_CHANGED->value)
        ->first();

    expect($event?->payload)->toMatchArray(['from' => null, 'to' => 'green']);
});

it('sets inactivity_alert_level to red and creates red notification for demandado process with recent activity', function (): void {
    $process = Process::factory()->create([
        'last_activity_date' => Carbon::now()->subDays(10)->toDateString(),
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'lawyer_role' => ProcessLawyerRole::DEFENDANT->value,
    ]);

    $job = new CheckInactiveProcessesJob;
    $job->handle(app(RecordSemaphoreTimelineEventService::class));

    $pivot = $process->organizations()->first()->pivot;
    expect($pivot->inactivity_alert_level)->toBe('red');

    $notification = OrganizationNotification::query()
        ->where('organization_id', $this->organization->id)
        ->where('notifiable_id', $process->id)
        ->where('notification_type', 'actividad_roja')
        ->first();

    expect($notification)->not->toBeNull();

    $event = ProcessTimelineEvent::query()
        ->where('process_id', $process->id)
        ->where('event_type', ProcessTimelineEventType::SEMAPHORE_CHANGED->value)
        ->first();

    expect($event?->payload)->toMatchArray(['from' => null, 'to' => 'red']);
});

it('sets inactivity_alert_level to yellow and creates yellow notification for demandado process inactive 45-89 days', function (): void {
    $process = Process::factory()->create([
        'last_activity_date' => Carbon::now()->subDays(50)->toDateString(),
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'lawyer_role' => ProcessLawyerRole::DEFENDANT->value,
    ]);

    $job = new CheckInactiveProcessesJob;
    $job->handle(app(RecordSemaphoreTimelineEventService::class));

    $pivot = $process->organizations()->first()->pivot;
    expect($pivot->inactivity_alert_level)->toBe('yellow');

    $notification = OrganizationNotification::query()
        ->where('organization_id', $this->organization->id)
        ->where('notifiable_id', $process->id)
        ->where('notification_type', 'actividad_amarilla')
        ->first();

    expect($notification)->not->toBeNull();

    $event = ProcessTimelineEvent::query()
        ->where('process_id', $process->id)
        ->where('event_type', ProcessTimelineEventType::SEMAPHORE_CHANGED->value)
        ->first();

    expect($event?->payload)->toMatchArray(['from' => null, 'to' => 'yellow']);
});

it('sets inactivity_alert_level to yellow and creates yellow notification for demandante process inactive 45-89 days', function (): void {
    $process = Process::factory()->create([
        'last_activity_date' => Carbon::now()->subDays(50)->toDateString(),
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'lawyer_role' => ProcessLawyerRole::PLAINTIFF->value,
    ]);

    $job = new CheckInactiveProcessesJob;
    $job->handle(app(RecordSemaphoreTimelineEventService::class));

    $pivot = $process->organizations()->first()->pivot;
    expect($pivot->inactivity_alert_level)->toBe('yellow');

    $notification = OrganizationNotification::query()
        ->where('organization_id', $this->organization->id)
        ->where('notifiable_id', $process->id)
        ->where('notification_type', 'inactividad_amarilla')
        ->first();

    expect($notification)->not->toBeNull();
});

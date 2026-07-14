<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Src\Application\Shared\Services\Notification\OrganizationNotificationRegistrationCutoffService;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

it('allows actuaciones discovered today even with old registration_date', function (): void {
    Carbon::setTestNow('2026-06-24 16:39:14');

    $organization = Organization::factory()->create();
    $process = Process::factory()->create();
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => Carbon::parse('2026-06-09'),
        'action_date' => Carbon::parse('2026-06-09'),
        'created_at' => now(),
    ]);

    $service = app(OrganizationNotificationRegistrationCutoffService::class);

    expect($service->isEligibleForAppActuacionNotification($action, $organization->id))->toBeTrue();
});

it('blocks stale actuaciones discovered before today', function (): void {
    Carbon::setTestNow('2026-06-24 16:39:14');

    $organization = Organization::factory()->create();
    $process = Process::factory()->create();
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => Carbon::parse('2026-06-09'),
        'action_date' => Carbon::parse('2026-06-09'),
        'created_at' => Carbon::parse('2026-06-10 10:00:00'),
    ]);

    $service = app(OrganizationNotificationRegistrationCutoffService::class);

    expect($service->isEligibleForAppActuacionNotification($action, $organization->id))->toBeFalse();
});

it('includes newly discovered actuaciones in digest pending filter', function (): void {
    Carbon::setTestNow('2026-06-24 12:00:00');

    $organization = Organization::factory()->create();
    $process = Process::factory()->create();
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $oldButNewToday = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => Carbon::parse('2026-06-09'),
        'action_date' => Carbon::parse('2026-06-09'),
        'created_at' => now(),
    ]);

    $stale = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => Carbon::parse('2026-06-01'),
        'action_date' => Carbon::parse('2026-06-01'),
        'created_at' => Carbon::parse('2026-06-02'),
    ]);

    $morphClass = $oldButNewToday->getMorphClass();
    $service = app(OrganizationNotificationRegistrationCutoffService::class);

    $priorAction = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => Carbon::parse('2026-06-20'),
        'action_date' => Carbon::parse('2026-06-20'),
        'created_at' => Carbon::parse('2026-06-20'),
    ]);

    $digest = \Src\Domain\Notification\Models\NotificationDigest::query()->create([
        'organization_id' => $organization->id,
        'data' => [],
        'email_sent_at' => Carbon::parse('2026-06-23'),
    ]);

    \Src\Domain\Notification\Models\OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $priorAction->id,
        'notifiable_type' => $morphClass,
        'notification_type' => 'actuacion',
        'is_viewed' => true,
        'is_notified' => true,
        'is_email_notified' => true,
        'notification_digest_id' => $digest->id,
    ]);

    \Src\Domain\Notification\Models\OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $oldButNewToday->id,
        'notifiable_type' => $morphClass,
        'notification_type' => 'actuacion',
        'is_viewed' => false,
        'is_notified' => false,
        'is_email_notified' => false,
    ]);

    \Src\Domain\Notification\Models\OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $stale->id,
        'notifiable_type' => $morphClass,
        'notification_type' => 'actuacion',
        'is_viewed' => false,
        'is_notified' => false,
        'is_email_notified' => false,
    ]);

    $ids = $organization->notifications()
        ->forActiveOrganizationProcesses($organization->id)
        ->where('is_email_notified', false)
        ->tap(fn ($query) => $service->applyDigestPendingCutoff($query, $organization->id))
        ->pluck('notifiable_id');

    expect($ids)->toContain($oldButNewToday->id);
    expect($ids)->not->toContain($stale->id);
});

it('NotificationDigestService includes actions discovered today below registration cutoff', function (): void {
    Carbon::setTestNow('2026-07-09 17:11:00');

    $organization = Organization::factory()->create();
    $organization->notificationChannels()->create([
        'channel_type' => 'email',
        'channel_value' => 'digest@example.com',
        'is_active' => true,
        'priority' => 1,
    ]);

    $process = Process::factory()->create(['process_number' => '76001418900720240117700']);
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $morphClass = (new ProcessAction)->getMorphClass();

    $priorAction = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => Carbon::parse('2026-07-09'),
        'action_date' => Carbon::parse('2026-07-09'),
        'created_at' => Carbon::parse('2026-07-09 09:00:00'),
    ]);

    $priorDigest = \Src\Domain\Notification\Models\NotificationDigest::query()->create([
        'organization_id' => $organization->id,
        'data' => [],
        'email_sent_at' => Carbon::parse('2026-07-09 09:59:00'),
    ]);

    \Src\Domain\Notification\Models\OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $priorAction->id,
        'notifiable_type' => $morphClass,
        'notification_type' => 'actuacion',
        'is_viewed' => true,
        'is_notified' => true,
        'is_email_notified' => true,
        'notification_digest_id' => $priorDigest->id,
    ]);

    $lateAction = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => Carbon::parse('2026-07-08'),
        'action_date' => Carbon::parse('2026-07-08'),
        'created_at' => Carbon::parse('2026-07-09 17:00:00'),
        'action' => 'Auto Decide',
    ]);

    \Src\Domain\Notification\Models\OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $lateAction->id,
        'notifiable_type' => $morphClass,
        'notification_type' => 'actuacion',
        'is_viewed' => false,
        'is_notified' => false,
        'is_email_notified' => false,
    ]);

    Illuminate\Support\Facades\Mail::fake();

    app(\Src\Application\Shared\Services\Notification\NotificationDigestService::class)
        ->sendDigest($organization);

    $pending = \Src\Domain\Notification\Models\OrganizationNotification::query()
        ->where('organization_id', $organization->id)
        ->where('notifiable_id', $lateAction->id)
        ->where('notification_type', 'actuacion')
        ->first();

    expect($pending->is_email_notified)->toBeTrue();
    expect($pending->notification_digest_id)->not->toBeNull();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

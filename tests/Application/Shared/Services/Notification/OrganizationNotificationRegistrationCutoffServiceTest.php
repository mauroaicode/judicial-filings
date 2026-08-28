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

it('blocks year-old actuaciones even when discovered today', function (): void {
    Carbon::setTestNow('2026-08-04 10:00:00');

    $organization = Organization::factory()->create();
    $process = Process::factory()->create();
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => Carbon::parse('2025-06-04'),
        'action_date' => Carbon::parse('2025-06-04'),
        'created_at' => now(),
    ]);

    $service = app(OrganizationNotificationRegistrationCutoffService::class);

    expect($service->isNewlyDiscoveredActuacion($action))->toBeFalse()
        ->and($service->isEligibleForAppActuacionNotification($action, $organization->id))->toBeFalse();
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

it('excludes year-old discovered-today actuaciones from digest pending filter', function (): void {
    Carbon::setTestNow('2026-08-04 10:00:00');

    $organization = Organization::factory()->create();
    $process = Process::factory()->create();
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $ancient = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => Carbon::parse('2025-06-04'),
        'action_date' => Carbon::parse('2025-06-04'),
        'created_at' => now(),
    ]);

    $recentLag = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => Carbon::parse('2026-07-20'),
        'action_date' => Carbon::parse('2026-07-20'),
        'created_at' => now(),
    ]);

    $morphClass = $ancient->getMorphClass();
    $service = app(OrganizationNotificationRegistrationCutoffService::class);

    $priorAction = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => Carbon::parse('2026-08-03'),
        'action_date' => Carbon::parse('2026-08-03'),
        'created_at' => Carbon::parse('2026-08-03'),
    ]);

    $digest = \Src\Domain\Notification\Models\NotificationDigest::query()->create([
        'organization_id' => $organization->id,
        'data' => [],
        'email_sent_at' => Carbon::parse('2026-08-03'),
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

    foreach ([$ancient, $recentLag] as $action) {
        \Src\Domain\Notification\Models\OrganizationNotification::query()->create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'notifiable_id' => $action->id,
            'notifiable_type' => $morphClass,
            'notification_type' => 'actuacion',
            'is_viewed' => false,
            'is_notified' => false,
            'is_email_notified' => false,
        ]);
    }

    $ids = $organization->notifications()
        ->forActiveOrganizationProcesses($organization->id)
        ->where('is_email_notified', false)
        ->tap(fn ($query) => $service->applyDigestPendingCutoff($query, $organization->id))
        ->pluck('notifiable_id');

    expect($ids)->toContain($recentLag->id)
        ->and($ids)->not->toContain($ancient->id);
});

it('includes manual Excel import actuaciones in digest pending despite old registration date', function (): void {
    Carbon::setTestNow('2026-08-28 10:00:00');

    $organization = Organization::factory()->create();
    $process = Process::factory()->create();
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $priorAction = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => Carbon::parse('2026-08-21'),
        'action_date' => Carbon::parse('2026-08-21'),
        'created_at' => Carbon::parse('2026-08-21'),
    ]);

    $manualImport = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => Carbon::parse('2026-07-08'),
        'action_date' => Carbon::parse('2026-07-08'),
        'action_registration_id' => -12345,
        'created_at' => Carbon::parse('2026-08-27'),
    ]);

    $morphClass = $manualImport->getMorphClass();
    $service = app(OrganizationNotificationRegistrationCutoffService::class);

    $digest = \Src\Domain\Notification\Models\NotificationDigest::query()->create([
        'organization_id' => $organization->id,
        'data' => [],
        'email_sent_at' => Carbon::parse('2026-08-21'),
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
        'notifiable_id' => $manualImport->id,
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

    expect($ids)->toContain($manualImport->id);
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

it('sends consolidated digest email to all active email channels', function (): void {
    $organization = Organization::factory()->create();
    $organization->notificationChannels()->createMany([
        [
            'channel_type' => 'email',
            'channel_value' => 'coordinadorajuridica@cooplaermita.com',
            'is_active' => true,
            'priority' => 1,
        ],
        [
            'channel_type' => 'email',
            'channel_value' => 'jhonwmanrique@hotmail.com',
            'is_active' => true,
            'priority' => 2,
        ],
        [
            'channel_type' => 'email',
            'channel_value' => 'inactive@example.com',
            'is_active' => false,
            'priority' => 3,
        ],
        [
            'channel_type' => 'internal',
            'channel_value' => 'internal',
            'is_active' => true,
            'priority' => 1,
        ],
    ]);

    $process = Process::factory()->create();
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => now(),
        'action_date' => now(),
    ]);

    \Src\Domain\Notification\Models\OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
        'is_notified' => false,
        'is_email_notified' => false,
    ]);

    Illuminate\Support\Facades\Mail::fake();

    app(\Src\Application\Shared\Services\Notification\NotificationDigestService::class)
        ->sendDigest($organization);

    Illuminate\Support\Facades\Mail::assertSent(
        \Src\Application\Shared\Mail\ConsolidatedJudicialActionsMailable::class,
        2,
    );

    Illuminate\Support\Facades\Mail::assertSent(
        \Src\Application\Shared\Mail\ConsolidatedJudicialActionsMailable::class,
        fn ($mail) => $mail->hasTo('coordinadorajuridica@cooplaermita.com'),
    );

    Illuminate\Support\Facades\Mail::assertSent(
        \Src\Application\Shared\Mail\ConsolidatedJudicialActionsMailable::class,
        fn ($mail) => $mail->hasTo('jhonwmanrique@hotmail.com'),
    );

    Illuminate\Support\Facades\Mail::assertNotSent(
        \Src\Application\Shared\Mail\ConsolidatedJudicialActionsMailable::class,
        fn ($mail) => $mail->hasTo('inactive@example.com'),
    );
});

afterEach(function (): void {
    Carbon::setTestNow();
});

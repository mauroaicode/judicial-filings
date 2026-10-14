<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Src\Application\Shared\Services\Notification\AlreadySentDigestActuacionChecker;
use Src\Application\Shared\Services\Notification\NotificationDigestService;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

it('detects a bare fijación as already sent when yesterday consolidado had fijación+auto', function (): void {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create(['process_number' => '11001400300220190080000']);
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $morphClass = (new ProcessAction)->getMorphClass();
    $regDate = Carbon::parse('2026-10-13');

    $yesterdayFijacion = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Fijacion Estado',
        'annotation' => 'Actuación Registrada el 13/10/2026 a las 12:13:59.',
        'registration_date' => $regDate,
        'action_date' => $regDate,
        'cons_action' => 10,
    ]);

    $yesterdayAuto = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Auto Resuelve Solicitud Remanentes',
        'annotation' => 'Resuelve',
        'registration_date' => $regDate,
        'action_date' => $regDate,
        'cons_action' => 9,
    ]);

    $digest = NotificationDigest::query()->create([
        'organization_id' => $organization->id,
        'data' => [],
        'email_sent_at' => Carbon::parse('2026-10-13 19:47:00'),
    ]);

    foreach ([$yesterdayFijacion, $yesterdayAuto] as $action) {
        OrganizationNotification::query()->create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'notifiable_id' => $action->id,
            'notifiable_type' => $morphClass,
            'notification_type' => 'actuacion',
            'is_viewed' => true,
            'is_notified' => true,
            'is_email_notified' => true,
            'notification_digest_id' => $digest->id,
        ]);
    }

    $morningBareFijacion = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Fijacion Estado',
        'annotation' => 'Actuación Registrada el 13/10/2026 a las 12:13:59.',
        'registration_date' => $regDate,
        'action_date' => $regDate,
        'cons_action' => 10,
    ]);

    $checker = app(AlreadySentDigestActuacionChecker::class);

    expect($checker->wasAlreadyIncludedInSentDigest($organization->id, $morningBareFijacion))->toBeTrue();
});

it('does not suppress an unrelated actuación on the same registration date', function (): void {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create(['process_number' => '76001233300020230079500']);
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $morphClass = (new ProcessAction)->getMorphClass();
    $regDate = Carbon::parse('2026-10-13');

    $sent = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Constancia Secretarial',
        'registration_date' => $regDate,
        'action_date' => $regDate,
    ]);

    $digest = NotificationDigest::query()->create([
        'organization_id' => $organization->id,
        'data' => [],
        'email_sent_at' => now(),
    ]);

    OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $sent->id,
        'notifiable_type' => $morphClass,
        'notification_type' => 'actuacion',
        'is_viewed' => true,
        'is_notified' => true,
        'is_email_notified' => true,
        'notification_digest_id' => $digest->id,
    ]);

    $pending = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Alegatos de Conclusion',
        'annotation' => 'Dar-alegatos Presentados por el Apoderado de la Parte Actora',
        'registration_date' => $regDate,
        'action_date' => $regDate,
    ]);

    expect(app(AlreadySentDigestActuacionChecker::class)
        ->wasAlreadyIncludedInSentDigest($organization->id, $pending))->toBeFalse();
});

it('NotificationDigestService omits already-sent bare fijación from a new consolidado', function (): void {
    Carbon::setTestNow('2026-10-14 12:05:00');
    Mail::fake();

    $organization = Organization::factory()->create();
    $organization->notificationChannels()->create([
        'channel_type' => 'email',
        'channel_value' => 'edwin@example.com',
        'is_active' => true,
        'priority' => 1,
    ]);

    $process = Process::factory()->create(['process_number' => '11001400300220190080000']);
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $morphClass = (new ProcessAction)->getMorphClass();
    $regDate = Carbon::parse('2026-10-13');

    $yesterdayFijacion = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Fijacion Estado',
        'registration_date' => $regDate,
        'action_date' => $regDate,
        'cons_action' => 10,
        'created_at' => Carbon::parse('2026-10-13 16:00:00'),
    ]);

    $yesterdayAuto = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Auto Resuelve Solicitud Remanentes',
        'registration_date' => $regDate,
        'action_date' => $regDate,
        'cons_action' => 9,
        'created_at' => Carbon::parse('2026-10-13 16:00:00'),
    ]);

    $priorDigest = NotificationDigest::query()->create([
        'organization_id' => $organization->id,
        'data' => [],
        'email_sent_at' => Carbon::parse('2026-10-13 19:47:00'),
    ]);

    foreach ([$yesterdayFijacion, $yesterdayAuto] as $action) {
        OrganizationNotification::query()->create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'notifiable_id' => $action->id,
            'notifiable_type' => $morphClass,
            'notification_type' => 'actuacion',
            'is_viewed' => true,
            'is_notified' => true,
            'is_email_notified' => true,
            'notification_digest_id' => $priorDigest->id,
        ]);
    }

    $morningBareFijacion = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Fijacion Estado',
        'annotation' => 'Actuación Registrada el 13/10/2026 a las 12:13:59.',
        'registration_date' => $regDate,
        'action_date' => $regDate,
        'cons_action' => 10,
        'created_at' => Carbon::parse('2026-10-14 07:00:00'),
    ]);

    $pending = OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $morningBareFijacion->id,
        'notifiable_type' => $morphClass,
        'notification_type' => 'actuacion',
        'is_viewed' => false,
        'is_notified' => false,
        'is_email_notified' => false,
    ]);

    app(NotificationDigestService::class)->sendDigest($organization);

    $pending->refresh();
    expect($pending->is_email_notified)->toBeTrue();

    $newDigests = NotificationDigest::query()
        ->where('organization_id', $organization->id)
        ->where('id', '!=', $priorDigest->id)
        ->count();

    expect($newDigests)->toBe(0);
    Mail::assertNothingSent();
});

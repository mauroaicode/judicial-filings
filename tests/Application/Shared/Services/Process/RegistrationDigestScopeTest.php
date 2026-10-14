<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Src\Application\Shared\Services\Process\ProcessSyncService;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

it('registration digest only sends the newly registered radicado and leaves other pending consolidado rows', function (): void {
    Mail::fake();

    $organization = Organization::factory()->create();
    $organization->notificationChannels()->create([
        'channel_type' => 'email',
        'channel_value' => 'cliente@example.com',
        'is_active' => true,
        'priority' => 1,
    ]);

    $pendingFromSync = Process::factory()->create(['process_number' => '76001310500220170063501']);
    $newlyRegistered = Process::factory()->create(['process_number' => '76001418900920250094800']);

    foreach ([$pendingFromSync, $newlyRegistered] as $process) {
        $process->organizations()->attach($organization->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
        ]);
    }

    $morphClass = (new ProcessAction)->getMorphClass();

    $syncAction = ProcessAction::factory()->create([
        'process_id' => $pendingFromSync->id,
        'action' => 'Fijacion Estado',
        'registration_date' => now(),
        'action_date' => now(),
    ]);

    $registrationAction = ProcessAction::factory()->create([
        'process_id' => $newlyRegistered->id,
        'action' => 'Auto de Trámite',
        'registration_date' => now(),
        'action_date' => now(),
    ]);

    OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $syncAction->id,
        'notifiable_type' => $morphClass,
        'notification_type' => 'actuacion',
        'is_viewed' => false,
        'is_notified' => false,
        'is_email_notified' => false,
    ]);

    OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $registrationAction->id,
        'notifiable_type' => $morphClass,
        'notification_type' => 'actuacion',
        'is_viewed' => false,
        'is_notified' => false,
        'is_email_notified' => false,
    ]);

    app(ProcessSyncService::class)->dispatchRegistrationDigestIfPending(
        $organization->id,
        [$newlyRegistered->process_number],
    );

    // OrganizationNotification uses a composite PK; reload by notifiable_id, not refresh().
    $syncNotification = OrganizationNotification::query()
        ->where('organization_id', $organization->id)
        ->where('notifiable_id', $syncAction->id)
        ->where('notification_type', 'actuacion')
        ->first();

    $registrationNotification = OrganizationNotification::query()
        ->where('organization_id', $organization->id)
        ->where('notifiable_id', $registrationAction->id)
        ->where('notification_type', 'actuacion')
        ->first();

    $digest = NotificationDigest::query()
        ->where('organization_id', $organization->id)
        ->latest('created_at')
        ->first();

    expect($registrationNotification)->not->toBeNull();
    expect($syncNotification)->not->toBeNull();
    expect($registrationNotification->is_email_notified)->toBeTrue();
    expect($registrationNotification->notification_digest_id)->not->toBeNull();
    expect($digest)->not->toBeNull();
    expect(collect($digest->data)->pluck('process_number')->unique()->values()->all())
        ->toBe([$newlyRegistered->process_number]);
    expect($syncNotification->is_email_notified)->toBeFalse();
    expect($syncNotification->notification_digest_id)->toBeNull();
});

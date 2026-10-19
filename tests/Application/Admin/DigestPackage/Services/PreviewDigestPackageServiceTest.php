<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Src\Application\Admin\DigestPackage\Services\PreviewDigestPackageService;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

it('excludes already-sent actuaciones resurfacing as actuacion_documento from package preview', function (): void {
    Carbon::setTestNow('2026-10-16 18:00:00');

    $organization = Organization::factory()->create([
        'name' => 'Niray Preview Ghost',
        'is_active' => true,
        'is_process_tracking_enabled' => true,
    ]);
    $organization->notificationChannels()->create([
        'channel_type' => 'email',
        'channel_value' => 'niray@example.com',
        'is_active' => true,
        'priority' => 1,
    ]);

    $process = Process::factory()->create(['process_number' => '76001310501220260006900']);
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => 'active',
    ]);

    $morphClass = (new ProcessAction)->getMorphClass();
    $regDate = Carbon::parse('2026-10-16');

    $sentAction = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Fijacion estado',
        'registration_date' => $regDate,
        'action_date' => $regDate,
    ]);

    $digest = NotificationDigest::query()->create([
        'organization_id' => $organization->id,
        'data' => [[
            'process_action_id' => $sentAction->id,
            'process_number' => $process->process_number,
            'action_text' => 'Fijacion Estado',
            'registration_date' => '16 de octubre de 2026',
        ]],
        'email_sent_at' => Carbon::parse('2026-10-16 17:39:00'),
    ]);

    OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $sentAction->id,
        'notifiable_type' => $morphClass,
        'notification_type' => 'actuacion',
        'is_viewed' => true,
        'is_notified' => true,
        'is_email_notified' => true,
        'notification_digest_id' => $digest->id,
        'email_notified_at' => $digest->email_sent_at,
    ]);

    // PDF upload after consolidado: reopens pending for the same actuación.
    OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $sentAction->id,
        'notifiable_type' => $morphClass,
        'notification_type' => 'actuacion_documento',
        'severity_color' => 'document',
        'is_viewed' => false,
        'is_notified' => false,
        'is_email_notified' => false,
        'created_at' => Carbon::parse('2026-10-16 17:53:00'),
    ]);

    $preview = app(PreviewDigestPackageService::class)->handle();
    $row = collect($preview->organizations)->firstWhere('organization_id', $organization->id);

    expect($row)->toBeNull();
});

it('includes truly pending actuaciones in package preview counts', function (): void {
    Carbon::setTestNow('2026-10-16 18:00:00');

    $organization = Organization::factory()->create([
        'name' => 'Org With Real Pending',
        'is_active' => true,
        'is_process_tracking_enabled' => true,
    ]);
    $organization->notificationChannels()->create([
        'channel_type' => 'email',
        'channel_value' => 'real@example.com',
        'is_active' => true,
        'priority' => 1,
    ]);

    $process = Process::factory()->create(['process_number' => '76001310500320230021400']);
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => 'active',
    ]);

    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Auto Decide',
        'registration_date' => now()->toDateString(),
        'action_date' => now()->toDateString(),
    ]);

    OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
        'is_notified' => false,
        'is_email_notified' => false,
    ]);

    $preview = app(PreviewDigestPackageService::class)->handle();
    $row = collect($preview->organizations)->firstWhere('organization_id', $organization->id);

    expect($row)->not->toBeNull()
        ->and($row->pending_processes)->toBe(1)
        ->and($row->pending_actions)->toBe(1);
});

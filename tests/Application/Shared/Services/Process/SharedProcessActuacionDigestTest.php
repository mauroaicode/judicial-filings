<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Src\Application\Shared\Services\Notification\NotificationDigestService;
use Src\Application\Shared\Services\Process\ProcessActionAlertNotificationService;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

it('keeps the sibling organization pending after the requesting organization digest is sent', function (): void {
    Mail::fake();

    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $process = Process::factory()->create(['is_manual_sync' => false]);
    $pivot = [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ];

    $process->organizations()->attach($organizationA->id, $pivot);
    $process->organizations()->attach($organizationB->id, $pivot);

    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => today(),
        'registration_date' => today(),
    ]);

    app(ProcessActionAlertNotificationService::class)->handle($action, $process);
    app(NotificationDigestService::class)->sendDigest($organizationA);

    $pendingForA = OrganizationNotification::query()
        ->where('organization_id', $organizationA->id)
        ->where('notifiable_id', $action->id)
        ->where('notification_type', 'actuacion')
        ->first();

    $pendingForB = OrganizationNotification::query()
        ->where('organization_id', $organizationB->id)
        ->where('notifiable_id', $action->id)
        ->where('notification_type', 'actuacion')
        ->first();

    expect($pendingForA)->not->toBeNull()
        ->and($pendingForA->is_email_notified)->toBeTrue()
        ->and($pendingForB)->not->toBeNull()
        ->and($pendingForB->is_email_notified)->toBeFalse();
});

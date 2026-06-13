<?php

use Src\Application\Shared\Services\Process\ProcessActionAlertNotificationService;
use Src\Domain\Keyword\Models\Keyword;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

it('detects severity color from matched keywords and stores it in notification', function () {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create();

    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    // Create a keyword with yellow severity
    Keyword::factory()->create([
        'organization_id' => $organization->id,
        'keyword' => 'traslado',
        'severity_color' => 'yellow',
    ]);

    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'annotation' => 'Se ordena el traslado de la liquidación',
    ]);

    $service = app(ProcessActionAlertNotificationService::class);
    $service->handle($action, $process);

    $notification = OrganizationNotification::query()
        ->where('organization_id', $organization->id)
        ->where('notifiable_id', $action->id)
        ->where('notification_type', 'actuacion_alerta')
        ->first();

    expect($notification)->not->toBeNull();
    expect($notification->severity_color)->toBe('yellow');
});

it('prioritizes red over yellow in notifications', function () {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create();

    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    // Yellow keyword
    Keyword::factory()->create([
        'organization_id' => $organization->id,
        'keyword' => 'traslado',
        'severity_color' => 'yellow',
    ]);

    // Red keyword
    Keyword::factory()->create([
        'organization_id' => $organization->id,
        'keyword' => 'sentencia',
        'severity_color' => 'red',
    ]);

    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'annotation' => 'Traslado para sentencia definitiva',
    ]);

    $service = app(ProcessActionAlertNotificationService::class);
    $service->handle($action, $process);

    $notification = OrganizationNotification::query()
        ->where('organization_id', $organization->id)
        ->where('notifiable_id', $action->id)
        ->where('notification_type', 'actuacion_alerta')
        ->first();

    expect($notification->severity_color)->toBe('red');
});

it('creates actuacion and actuacion_registro notifications during registration import', function (): void {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create();

    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Auto admisorio',
        'action_date' => now(),
        'registration_date' => now(),
    ]);

    $service = app(ProcessActionAlertNotificationService::class);
    $service->handleForOrganizationRegistration($action, $process, $organization->id);

    expect(OrganizationNotification::query()
        ->where('organization_id', $organization->id)
        ->where('notifiable_id', $action->id)
        ->where('notification_type', 'actuacion')
        ->exists())->toBeTrue();

    expect(OrganizationNotification::query()
        ->where('organization_id', $organization->id)
        ->where('notifiable_id', $action->id)
        ->where('notification_type', 'actuacion_registro')
        ->exists())->toBeTrue();
});

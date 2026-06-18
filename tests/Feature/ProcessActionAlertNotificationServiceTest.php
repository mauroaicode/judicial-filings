<?php

use Src\Application\Shared\Services\Process\ProcessActionAlertNotificationService;
use Src\Domain\Keyword\Enums\KeywordStatus;
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
        'status' => KeywordStatus::ACTIVE,
    ]);

    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'annotation' => 'Se ordena el traslado de la liquidación',
        'action_date' => now(),
        'registration_date' => now(),
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
        'status' => KeywordStatus::ACTIVE,
    ]);

    // Red keyword
    Keyword::factory()->create([
        'organization_id' => $organization->id,
        'keyword' => 'sentencia',
        'severity_color' => 'red',
        'status' => KeywordStatus::ACTIVE,
    ]);

    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'annotation' => 'Traslado para sentencia definitiva',
        'action_date' => now(),
        'registration_date' => now(),
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

it('skips duplicate notifications for the same actuacion content across instances', function (): void {
    $organization = Organization::factory()->create();
    $processNumber = '76001400303420230073500';

    $richProcess = Process::factory()->create(['process_number' => $processNumber]);
    $phantomProcess = Process::factory()->create(['process_number' => $processNumber]);

    $richProcess->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $phantomProcess->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $firstAction = ProcessAction::factory()->create([
        'process_id' => $richProcess->id,
        'action_registration_id' => 1111111111,
        'action' => 'Fijacion estado',
        'annotation' => 'Actuación registrada el 12/06/2026 a las 18:18:47.',
        'action_date' => now(),
        'registration_date' => now(),
    ]);

    $duplicateAction = ProcessAction::factory()->create([
        'process_id' => $phantomProcess->id,
        'action_registration_id' => 2222222222,
        'action' => 'Fijacion estado',
        'annotation' => 'Actuación registrada el 12/06/2026 a las 18:18:47.',
        'action_date' => $firstAction->action_date,
        'registration_date' => $firstAction->registration_date,
    ]);

    $service = app(ProcessActionAlertNotificationService::class);
    $service->handle($firstAction, $richProcess);
    $service->handle($duplicateAction, $phantomProcess);

    expect(OrganizationNotification::query()
        ->where('organization_id', $organization->id)
        ->where('notification_type', 'actuacion')
        ->count())->toBe(1);
});

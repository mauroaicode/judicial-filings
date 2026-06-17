<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Src\Application\Shared\Services\Process\RepairPhantomProcessInstancesService;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

it('removes duplicate actuaciones across phantom instances and keeps canonical on rich process', function (): void {
    $organization = Organization::factory()->create();
    $processNumber = fake()->unique()->numerify('#######################');

    $richProcess = Process::factory()->create([
        'process_number' => $processNumber,
        'court' => 'JUZGADO 034 CIVIL MUNICIPAL DE CALI',
        'department' => 'VALLE DEL CAUCA',
        'process_date' => '2023-08-30',
        'litigants' => 'Demandante: JAIRO ALFONSO LLANOS RUIZ',
    ]);

    $phantomProcess = Process::factory()->create([
        'process_number' => $processNumber,
        'court' => 'JUZGADO 034 CIVIL MUNICIPAL DE CALI',
        'department' => 'VALLE DEL CAUCA',
        'process_date' => '2023-08-30',
        'litigants' => null,
    ]);

    $richProcess->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $canonical = ProcessAction::factory()->create([
        'process_id' => $richProcess->id,
        'action_registration_id' => 1111111111,
        'action' => 'Fijacion estado',
        'annotation' => 'Actuación registrada el 12/06/2026 a las 18:18:47.',
        'action_date' => '2026-06-12',
        'registration_date' => '2026-06-12',
    ]);

    $duplicate = ProcessAction::factory()->create([
        'process_id' => $phantomProcess->id,
        'action_registration_id' => 2222222222,
        'action' => 'Fijacion estado',
        'annotation' => 'Actuación registrada el 12/06/2026 a las 18:18:47.',
        'action_date' => '2026-06-12',
        'registration_date' => '2026-06-12',
    ]);

    OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $canonical->id,
        'notifiable_type' => (new ProcessAction)->getMorphClass(),
        'notification_type' => 'actuacion',
        'severity_color' => null,
        'is_viewed' => false,
        'is_notified' => false,
    ]);

    OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $duplicate->id,
        'notifiable_type' => (new ProcessAction)->getMorphClass(),
        'notification_type' => 'actuacion',
        'severity_color' => null,
        'is_viewed' => false,
        'is_notified' => false,
    ]);

    $result = app(RepairPhantomProcessInstancesService::class)->repairRadicado($processNumber);

    expect($result->actionsRemoved)->toBe(1);
    expect($result->phantomInstancesDetected)->toBe(1);
    expect(ProcessAction::query()->find($duplicate->id))->toBeNull();
    expect(ProcessAction::query()->find($canonical->id))->not->toBeNull();
    expect(OrganizationNotification::query()
        ->where('organization_id', $organization->id)
        ->where('notification_type', 'actuacion')
        ->count())->toBe(1);
});

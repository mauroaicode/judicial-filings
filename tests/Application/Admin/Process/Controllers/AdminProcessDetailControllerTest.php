<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin-detail@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);
});

it('requires authentication to view admin process detail', function (): void {
    $process = Process::factory()->create();

    $response = $this->getJson("/api/admin/processes/{$process->id}");

    $response->assertStatus(401);
});

it('returns admin process detail with subjects and interested organizations', function (): void {
    $orgPlaintiff = Organization::factory()->create(['type' => 'natural', 'name' => 'Ernesto Bedoya Cruz']);
    $orgDefendant = Organization::factory()->create(['type' => 'juridical', 'name' => 'Metro Cali Sa']);

    $process = Process::factory()->create([
        'last_activity_date' => now()->subDays(5),
    ]);

    $process->organizations()->attach($orgPlaintiff->id, [
        'interest_date' => '2026-04-10',
        'is_active' => true,
        'status' => OrganizationProcessStatus::ACTIVE->value,
        'lawyer_role' => 'plaintiff',
        'inactivity_alert_level' => null,
    ]);
    $process->organizations()->attach($orgDefendant->id, [
        'interest_date' => '2026-04-22',
        'is_active' => false,
        'status' => OrganizationProcessStatus::INACTIVE->value,
        'lawyer_role' => 'defendant',
        'inactivity_alert_level' => 'yellow',
    ]);

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA XYZ',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/processes/{$process->id}?organization_id={$orgDefendant->id}");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'process' => [
            'id',
            'process_id',
            'process_number',
            'court',
            'process_class',
            'last_activity_date',
            'alert_level',
            'lawyer_role',
        ],
        'subjects',
        'subjects_summary' => [
            'plaintiffs_count',
            'defendants_count',
            'others_count',
            'subjects_count',
            'plaintiff',
            'defendant',
            'other_subject',
            'plaintiffs',
            'defendants',
            'others',
        ],
        'organizations' => [
            'count',
            'items' => [
                '*' => [
                    'id',
                    'name',
                    'type',
                    'type_label',
                    'lawyer_role',
                    'lawyer_role_label',
                    'status',
                    'status_label',
                    'interest_date',
                    'inactivity_alert_level',
                    'alert_level',
                ],
            ],
        ],
    ]);

    expect($response->json('process.status_label'))->toBe('Activo');
    expect($response->json('process.alert_level'))->toBe('red');
    expect($response->json('process.lawyer_role'))->toBe('Demandado');
    expect($response->json('subjects'))->toHaveCount(2);
    expect($response->json('subjects_summary.plaintiffs_count'))->toBe(1);
    expect($response->json('subjects_summary.defendants_count'))->toBe(1);

    expect($response->json('organizations.count'))->toBe(2);

    $items = collect($response->json('organizations.items'));
    expect($items->firstWhere('id', $orgDefendant->id)['lawyer_role'])->toBe('defendant');
    expect($items->firstWhere('id', $orgDefendant->id)['status'])->toBe('inactive');
    expect($items->firstWhere('id', $orgDefendant->id)['inactivity_alert_level'])->toBe('yellow');
    expect($items->firstWhere('id', $orgDefendant->id)['alert_level'])->toBe('red');
    expect($items->firstWhere('id', $orgDefendant->id)['type'])->toBe('juridical');

    expect($items->firstWhere('id', $orgPlaintiff->id)['lawyer_role'])->toBe('plaintiff');
    expect($items->firstWhere('id', $orgPlaintiff->id)['status'])->toBe('active');
    expect($items->firstWhere('id', $orgPlaintiff->id)['inactivity_alert_level'])->toBeNull();
    expect($items->firstWhere('id', $orgPlaintiff->id)['alert_level'])->toBe('green');
});

it('shows process as active when at least one organization is still interested', function (): void {
    $inactiveOrg = Organization::factory()->create();
    $activeOrg = Organization::factory()->create();

    $process = Process::factory()->create();

    $process->organizations()->attach($inactiveOrg->id, [
        'interest_date' => '2026-04-22',
        'is_active' => false,
        'status' => OrganizationProcessStatus::INACTIVE->value,
        'lawyer_role' => 'defendant',
    ]);
    $process->organizations()->attach($activeOrg->id, [
        'interest_date' => '2026-03-18',
        'is_active' => true,
        'status' => OrganizationProcessStatus::ACTIVE->value,
        'lawyer_role' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/processes/{$process->id}?organization_id={$inactiveOrg->id}");

    $response->assertStatus(200);
    expect($response->json('process.status_label'))->toBe('Activo');
    expect($response->json('process.lawyer_role'))->toBe('Demandado');

    $items = collect($response->json('organizations.items'));
    expect($items->firstWhere('id', $inactiveOrg->id)['status'])->toBe('inactive');
    expect($items->firstWhere('id', $activeOrg->id)['status'])->toBe('active');
});

it('requires authentication to update admin process information', function (): void {
    $process = Process::factory()->create();

    $response = $this->patchJson("/api/admin/processes/{$process->id}", [
        'court' => 'Juzgado Civil',
    ]);

    $response->assertStatus(401);
});

it('updates process general information and returns iso process date', function (): void {
    $process = Process::factory()->create([
        'court' => 'JUZGADO CIVIL',
        'speaker' => 'Maria Gomez',
        'department' => 'VALLE DEL CAUCA',
        'process_type' => 'ORDINARIO',
        'process_class' => 'CIVIL',
        'subclass_process' => 'SINGULAR',
        'location' => 'Cali',
        'process_date' => '2026-01-01',
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/api/admin/processes/{$process->id}", [
            'court' => 'Juzgado 017 Administrativo De Cali',
            'speaker' => 'Ana Perez',
            'process_class' => 'Verbal',
            'subclass_process' => null,
            'location' => 'Bogota',
            'process_date' => '2022-03-18',
        ]);

    $response->assertStatus(200);
    $response->assertJsonPath('message', __('process.updated_successfully'));
    $response->assertJsonPath('process.process_date_iso', '2022-03-18');
    $response->assertJsonPath('process.speaker', 'Ana Perez');
    $response->assertJsonPath('process.location', 'Bogota');
    $response->assertJsonPath('process.subclass_process', null);

    $this->assertDatabaseHas('processes', [
        'id' => $process->id,
        'court' => 'Juzgado 017 Administrativo De Cali',
        'speaker' => 'Ana Perez',
        'process_class' => 'Verbal',
        'subclass_process' => null,
        'location' => 'Bogota',
        'process_date' => '2022-03-18',
        'process_type' => 'ORDINARIO',
    ]);
});

it('rejects empty process updates and impossible process dates', function (): void {
    $process = Process::factory()->create();

    $this->actingAs($this->user)
        ->patchJson("/api/admin/processes/{$process->id}", [])
        ->assertStatus(422);

    $this->actingAs($this->user)
        ->patchJson("/api/admin/processes/{$process->id}", [
            'process_date' => '2851-10-22',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['process_date']);
});

it('returns 404 when updating a missing process', function (): void {
    $missingId = '00000000-0000-0000-0000-000000000000';

    $this->actingAs($this->user)
        ->patchJson("/api/admin/processes/{$missingId}", [
            'court' => 'Juzgado Civil',
        ])
        ->assertStatus(404);
});

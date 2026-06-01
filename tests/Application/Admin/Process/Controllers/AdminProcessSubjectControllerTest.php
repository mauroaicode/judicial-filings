<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin-process-subjects@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->process = Process::factory()->create();
});

it('requires authentication to sync process subjects', function (): void {
    $response = $this->putJson("/api/admin/processes/{$this->process->id}/subjects", [
        'subjects' => [
            ['subject_type' => 'demandado', 'name_or_business_name' => 'Juan Perez'],
        ],
    ]);

    $response->assertStatus(401);
});

it('validates subjects array is required', function (): void {
    $response = $this->actingAs($this->user)
        ->putJson("/api/admin/processes/{$this->process->id}/subjects", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['subjects']);
});

it('creates manual subjects in a single request with formatted subject type', function (): void {
    $response = $this->actingAs($this->user)
        ->putJson("/api/admin/processes/{$this->process->id}/subjects", [
            'subjects' => [
                [
                    'subject_type' => 'DEMANDANTE',
                    'name_or_business_name' => 'ernesto bedoya cruz',
                ],
                [
                    'subject_type' => 'apoderado',
                    'name_or_business_name' => 'andres felipe romero manchola',
                ],
            ],
        ]);

    $response->assertStatus(200);
    $response->assertJsonPath('message', __('process.subjects_synced_successfully'));
    expect($response->json('subjects'))->toHaveCount(2);

    $plaintiff = collect($response->json('subjects'))->firstWhere('subject_type', 'Demandante');
    expect($plaintiff['name_or_business_name'])->toBe('Ernesto Bedoya Cruz');
    expect($plaintiff['is_manual'])->toBeTrue();
    expect($plaintiff['subject_registration_id'])->toBeNull();

    $this->assertDatabaseHas('process_subjects', [
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'Ernesto Bedoya Cruz',
    ]);
});

it('updates an existing subject by id including judicial api subjects', function (): void {
    $apiSubject = ProcessSubject::factory()->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'Metro Cali Sa',
    ]);
    $this->process->subjects()->attach($apiSubject->id);

    $response = $this->actingAs($this->user)
        ->putJson("/api/admin/processes/{$this->process->id}/subjects", [
            'subjects' => [
                [
                    'id' => $apiSubject->id,
                    'subject_type' => 'demandado principal',
                    'name_or_business_name' => 'Metro Cali Sa Acuerdo',
                ],
            ],
        ]);

    $response->assertStatus(200);

    $updated = collect($response->json('subjects'))->firstWhere('id', $apiSubject->id);
    expect($updated['subject_type'])->toBe('Demandado Principal');
    expect($updated['name_or_business_name'])->toBe('Metro Cali Sa Acuerdo');
    expect($updated['is_manual'])->toBeFalse();
    expect($updated['subject_registration_id'])->toBe($apiSubject->subject_registration_id);

    $this->assertDatabaseHas('process_subjects', [
        'id' => $apiSubject->id,
        'subject_type' => 'Demandado Principal',
        'name_or_business_name' => 'Metro Cali Sa Acuerdo',
        'subject_registration_id' => $apiSubject->subject_registration_id,
    ]);
});

it('creates and updates subjects in the same request', function (): void {
    $manualSubject = ProcessSubject::factory()->create([
        'subject_registration_id' => null,
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'Nombre Antiguo',
    ]);
    $this->process->subjects()->attach($manualSubject->id);

    $response = $this->actingAs($this->user)
        ->putJson("/api/admin/processes/{$this->process->id}/subjects", [
            'subjects' => [
                [
                    'id' => $manualSubject->id,
                    'subject_type' => 'demandante',
                    'name_or_business_name' => 'Nombre Actualizado',
                ],
                [
                    'subject_type' => 'demandado',
                    'name_or_business_name' => 'nueva empresa sas',
                ],
            ],
        ]);

    $response->assertStatus(200);
    expect($response->json('subjects'))->toHaveCount(2);

    $this->assertDatabaseHas('process_subjects', [
        'id' => $manualSubject->id,
        'name_or_business_name' => 'Nombre Actualizado',
    ]);

    $newSubject = collect($response->json('subjects'))
        ->first(fn (array $s): bool => ($s['name_or_business_name'] ?? '') === 'Nueva Empresa Sas');

    expect($newSubject['is_manual'])->toBeTrue();
});

it('returns 404 when subject id is not linked to the process', function (): void {
    $otherProcess = Process::factory()->create();
    $subject = ProcessSubject::factory()->forProcess($otherProcess)->create();

    $response = $this->actingAs($this->user)
        ->putJson("/api/admin/processes/{$this->process->id}/subjects", [
            'subjects' => [
                [
                    'id' => $subject->id,
                    'subject_type' => 'demandado',
                    'name_or_business_name' => 'Alguien',
                ],
            ],
        ]);

    $response->assertStatus(404);
});

it('returns 404 when process does not exist', function (): void {
    $missingId = '00000000-0000-0000-0000-000000000000';

    $response = $this->actingAs($this->user)
        ->putJson("/api/admin/processes/{$missingId}/subjects", [
            'subjects' => [
                ['subject_type' => 'demandado', 'name_or_business_name' => 'Test'],
            ],
        ]);

    $response->assertStatus(404);
});

it('appends new manual subjects without resending existing ones', function (): void {
    ProcessSubject::factory()->forProcess($this->process)->create([
        'subject_registration_id' => 88888801,
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'Sujeto Existente Judicial',
    ]);

    $response = $this->actingAs($this->user)
        ->putJson("/api/admin/processes/{$this->process->id}/subjects", [
            'subjects' => [
                [
                    'subject_type' => 'testigo',
                    'name_or_business_name' => 'maria lopez',
                ],
            ],
        ]);

    $response->assertStatus(200);
    expect($response->json('subjects'))->toHaveCount(2);

    $newSubject = collect($response->json('subjects'))
        ->firstWhere('name_or_business_name', 'Maria Lopez');

    expect($newSubject['is_manual'])->toBeTrue();
    expect($newSubject['subject_registration_id'])->toBeNull();
});

it('requires authentication to delete a manual process subject', function (): void {
    $subject = ProcessSubject::factory()->create(['subject_registration_id' => null]);
    $this->process->subjects()->attach($subject->id);

    $response = $this->deleteJson("/api/admin/processes/{$this->process->id}/subjects/{$subject->id}");

    $response->assertStatus(401);
});

it('deletes a manual subject from the process', function (): void {
    $subject = ProcessSubject::factory()->create([
        'subject_registration_id' => null,
        'subject_type' => 'Testigo',
        'name_or_business_name' => 'Maria Lopez',
    ]);
    $this->process->subjects()->attach($subject->id);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/admin/processes/{$this->process->id}/subjects/{$subject->id}");

    $response->assertStatus(200);
    $response->assertJson([
        'message' => __('process.subject_deleted_successfully'),
    ]);

    expect($this->process->subjects()->where('process_subjects.id', $subject->id)->exists())->toBeFalse();
    expect(ProcessSubject::query()->where('id', $subject->id)->exists())->toBeFalse();
});

it('rejects deletion of judicial api subjects', function (): void {
    $subject = ProcessSubject::factory()->create();
    $this->process->subjects()->attach($subject->id);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/admin/processes/{$this->process->id}/subjects/{$subject->id}");

    $response->assertStatus(422);
    expect($this->process->subjects()->where('process_subjects.id', $subject->id)->exists())->toBeTrue();
});

it('returns 404 when deleting a subject not linked to the process', function (): void {
    $subject = ProcessSubject::factory()->create(['subject_registration_id' => null]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/admin/processes/{$this->process->id}/subjects/{$subject->id}");

    $response->assertStatus(404);
});

it('does not remove existing subjects that are omitted from the payload', function (): void {
    $existing = ProcessSubject::factory()->forProcess($this->process)->create([
        'subject_registration_id' => 99999901,
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'Sujeto Judicial',
    ]);

    $response = $this->actingAs($this->user)
        ->putJson("/api/admin/processes/{$this->process->id}/subjects", [
            'subjects' => [
                [
                    'subject_type' => 'demandante',
                    'name_or_business_name' => 'Solo Nuevo Manual',
                ],
            ],
        ]);

    $response->assertStatus(200);
    expect($response->json('subjects'))->toHaveCount(2);
    expect(collect($response->json('subjects'))->pluck('id'))->toContain($existing->id);
});

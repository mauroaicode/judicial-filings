<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);

    // Attach user to organization
    $this->appUser->organizations()->attach($this->organization->id, [
        'is_owner' => true,
    ]);
});

it('requires authentication to view process detail', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->getJson("/api/app-user/processes/{$process->id}");

    $response->assertStatus(401);
});

it('returns process detail with subjects', function (): void {
    $process = Process::factory()->create([
        'process_number' => '76001333301320170009301',
        'court' => 'JUZGADO 017 ADMINISTRATIVO',
        'department' => 'VALLE DEL CAUCA',
        'process_type' => 'Ordinario',
        'process_class' => 'ACCION DE REPARACION DIRECTA',
        'subclass_process' => 'SIN SUBCLASE',
        'litigants' => 'Test litigants',
        'process_date' => '2024-01-15',
        'last_activity_date' => '2024-02-20',
        'location' => 'DESPACHO',
        'filing_content' => 'Test content',
        'is_private' => false,
        'has_multiple_instances' => true,
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $subject1 = ProcessSubject::factory()->create([
        'process_id' => $process->id,
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ GARCIA',
        'identification' => '1234567890',
        'is_cited' => false,
    ]);

    $subject2 = ProcessSubject::factory()->create([
        'process_id' => $process->id,
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA XYZ S.A.',
        'identification' => '9001234567',
        'is_cited' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'process' => [
            'id',
            'process_id',
            'process_number',
            'court',
            'department',
            'process_type',
            'process_class',
            'subclass_process',
            'litigants',
            'process_date',
            'last_activity_date',
            'location',
            'filing_content',
            'is_private',
            'has_multiple_instances',
            'last_api_update',
            'status_label',
            'created_at',
            'updated_at',
            'term_start_date',
            'term_end_date',
        ],
        'subjects' => [
            '*' => [
                'id',
                'subject_registration_id',
                'subject_type',
                'is_cited',
                'identification',
                'name_or_business_name',
            ],
        ],
    ]);

    expect($response->json('process.id'))->toBe($process->id);
    expect($response->json('process.process_number'))->toBe('76001333301320170009301');
    expect($response->json('subjects'))->toHaveCount(2);

    $subjectIds = collect($response->json('subjects'))->pluck('id')->toArray();
    expect($subjectIds)->toContain($subject1->id);
    expect($subjectIds)->toContain($subject2->id);
});

it('returns 404 when process does not exist', function (): void {
    $nonExistentId = '00000000-0000-0000-0000-000000000000';

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$nonExistentId}");

    $response->assertStatus(404);
});

it('returns 404 when process belongs to another organization', function (): void {
    $otherOrganization = Organization::factory()->create();
    $process = Process::factory()->create();

    $process->organizations()->attach($otherOrganization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}");

    $response->assertStatus(404);
});

it('returns error when user has no organization', function (): void {
    $userWithoutOrg = AppUser::factory()->create([
        'email' => 'noorg@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);

    $process = Process::factory()->create();

    $response = $this->actingAs($userWithoutOrg)
        ->getJson("/api/app-user/processes/{$process->id}");

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('process.user_has_no_organization')],
        'code' => 422,
    ]);
});

it('returns empty subjects array when process has no subjects', function (): void {
    $process = Process::factory()->create();

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}");

    $response->assertStatus(200);
    expect($response->json('subjects'))->toBeEmpty();
});

it('converts process fields to title case', function (): void {
    $process = Process::factory()->create([
        'court' => 'JUZGADO 017 ADMINISTRATIVO DE CALI',
        'department' => 'VALLE DEL CAUCA',
        'process_type' => 'ORDINARIO',
        'process_class' => 'ACCION DE REPARACION DIRECTA',
        'subclass_process' => 'SIN SUBCLASE DE PROCESO',
        'location' => 'DESPACHO PRINCIPAL',
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}");

    $response->assertStatus(200);
    expect($response->json('process.court'))->toBe('Juzgado 017 Administrativo de Cali');
    expect($response->json('process.department'))->toBe('Valle del Cauca');
    expect($response->json('process.process_type'))->toBe('Ordinario');
    expect($response->json('process.process_class'))->toBe('Accion de Reparacion Directa');
    expect($response->json('process.subclass_process'))->toBe('Sin Subclase de Proceso');
    expect($response->json('process.location'))->toBe('Despacho Principal');
});

it('converts subject names to title case', function (): void {
    $process = Process::factory()->create();

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    ProcessSubject::factory()->create([
        'process_id' => $process->id,
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN CARLOS PEREZ GARCIA',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}");

    $response->assertStatus(200);
    expect($response->json('subjects.0.name_or_business_name'))->toBe('Juan Carlos Perez Garcia');
});

it('returns correct status label for active process', function (): void {
    $process = Process::factory()->create();

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}");

    $response->assertStatus(200);
    expect($response->json('process.status_label'))->toBe(__('enums.process_status.active'));
});

it('returns correct status label for inactive process', function (): void {
    $process = Process::factory()->create();

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}");

    $response->assertStatus(200);
    expect($response->json('process.status_label'))->toBe(__('enums.process_status.inactive'));
});

it('formats dates correctly', function (): void {
    $process = Process::factory()->create([
        'process_date' => '2024-01-15',
        'last_activity_date' => '2024-02-20',
        'last_api_update' => '2024-03-10 14:30:00',
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'created_at' => '2024-01-01 10:00:00',
        'updated_at' => '2024-01-01 10:00:00',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}");

    $response->assertStatus(200);
    expect($response->json('process.process_date'))->toBe(\Src\Application\Shared\Helpers\DateFormatHelper::formatDate(\Illuminate\Support\Carbon::parse('2024-01-15')));
    expect($response->json('process.last_activity_date'))->toBe(\Src\Application\Shared\Helpers\DateFormatHelper::formatDate(\Illuminate\Support\Carbon::parse('2024-02-20')));
    expect($response->json('process.last_api_update'))->toBe(\Src\Application\Shared\Helpers\DateFormatHelper::formatDateTime(\Illuminate\Support\Carbon::parse('2024-03-10 14:30:00')));
    expect($response->json('process.created_at'))->toBe(\Src\Application\Shared\Helpers\DateFormatHelper::formatDateTime(\Illuminate\Support\Carbon::parse('2024-01-01 10:00:00')));
});

it('handles nullable fields correctly', function (): void {
    $process = Process::factory()->create([
        'subclass_process' => null,
        'litigants' => null,
        'last_activity_date' => null,
        'location' => null,
        'filing_content' => null,
        'last_api_update' => null,
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}");

    $response->assertStatus(200);
    expect($response->json('process.subclass_process'))->toBeNull();
    expect($response->json('process.litigants'))->toBeNull();
    expect($response->json('process.last_activity_date'))->toBeNull();
    expect($response->json('process.location'))->toBeNull();
    expect($response->json('process.filing_content'))->toBeNull();
    expect($response->json('process.last_api_update'))->toBeNull();
});

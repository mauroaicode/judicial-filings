<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create([
        'name' => 'Org '.Str::uuid(),
    ]);
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

it('includes plaintiff and defendant in process response', function (): void {
    $process = Process::factory()->create();

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ',
    ]);

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA XYZ S.A.',
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes');

    $response->assertStatus(200);
    expect($response->json('data.0.plaintiff'))->toBe('Juan Perez');
    expect($response->json('data.0.defendant'))->toBe('Empresa Xyz S.A.');
});

it('shows indicator when there are multiple plaintiffs', function (): void {
    $process = Process::factory()->create();

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ',
    ]);

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'MARIA GARCIA',
    ]);

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA XYZ S.A.',
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes');

    $response->assertStatus(200);
    expect($response->json('data.0.plaintiff'))->toContain('(+1)');
    expect($response->json('data.0.defendant'))->toBe('Empresa Xyz S.A.');
});

it('shows indicator when there are multiple defendants', function (): void {
    $process = Process::factory()->create();

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ',
    ]);

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA XYZ S.A.',
    ]);

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA ABC S.A.',
    ]);

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA DEF S.A.',
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes');

    $response->assertStatus(200);
    expect($response->json('data.0.plaintiff'))->toBe('Juan Perez');
    expect($response->json('data.0.defendant'))->toContain('(+2)');
});

it('shows null when process has no subjects', function (): void {
    $process = Process::factory()->create();
    // No subjects attached

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes');

    $response->assertStatus(200);
    expect($response->json('data.0.plaintiff'))->toBeNull();
    expect($response->json('data.0.defendant'))->toBeNull();
});

it('shows null when process has no plaintiff', function (): void {
    $process = Process::factory()->create();

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA XYZ S.A.',
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes');

    $response->assertStatus(200);
    expect($response->json('data.0.plaintiff'))->toBeNull();
    expect($response->json('data.0.defendant'))->toBe('Empresa Xyz S.A.');
});

it('shows null when process has no defendant', function (): void {
    $process = Process::factory()->create();

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ',
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes');

    $response->assertStatus(200);
    expect($response->json('data.0.plaintiff'))->toBe('Juan Perez');
    expect($response->json('data.0.defendant'))->toBeNull();
});

it('returns full plaintiffs and defendants lists for tooltip', function (): void {
    $process = Process::factory()->create();

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'MARIA GARCIA',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA XYZ S.A.',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA ABC S.A.',
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes');

    $response->assertStatus(200);
    expect($response->json('data.0.plaintiff'))->toContain('(+1)');
    expect($response->json('data.0.defendant'))->toContain('(+1)');
    expect($response->json('data.0.plaintiffs'))->toBe(['Juan Perez', 'Maria Garcia']);
    expect($response->json('data.0.defendants'))->toBe(['Empresa Abc S.A.', 'Empresa Xyz S.A.']);
});

it('includes other subjects count in process list response', function (): void {
    $process = Process::factory()->create();

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA XYZ S.A.',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Apoderado',
        'name_or_business_name' => 'ANDRES ROMERO',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'No Recurrente',
        'name_or_business_name' => 'ANA MARIA MARCAYATA',
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes');

    $response->assertStatus(200);
    expect($response->json('data.0.plaintiffs_count'))->toBe(1);
    expect($response->json('data.0.defendants_count'))->toBe(1);
    expect($response->json('data.0.others_count'))->toBe(2);
    expect($response->json('data.0.subjects_count'))->toBe(4);
    expect($response->json('data.0.other_subject'))->toContain('(+1)');
    expect($response->json('data.0.others'))->toContain('Ana Maria Marcayata', 'Andres Romero');
});

it('shows correct count indicator for multiple plaintiffs and defendants', function (): void {
    $process = Process::factory()->create();

    // Create 3 plaintiffs
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'MARIA GARCIA',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'CARLOS LOPEZ',
    ]);

    // Create 5 defendants
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA XYZ S.A.',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA ABC S.A.',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA DEF S.A.',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA GHI S.A.',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA JKL S.A.',
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes');

    $response->assertStatus(200);
    expect($response->json('data.0.plaintiff'))->toContain('(+2)'); // 3 total - 1 shown = 2 more
    expect($response->json('data.0.defendant'))->toContain('(+4)'); // 5 total - 1 shown = 4 more
});

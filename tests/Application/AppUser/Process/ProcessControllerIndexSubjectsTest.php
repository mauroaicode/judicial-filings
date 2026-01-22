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

it('includes plaintiff and defendant in process response', function (): void {
    $process = Process::factory()->create();

    ProcessSubject::factory()->create([
        'process_id' => $process->id,
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ',
    ]);

    ProcessSubject::factory()->create([
        'process_id' => $process->id,
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

    ProcessSubject::factory()->create([
        'process_id' => $process->id,
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ',
    ]);

    ProcessSubject::factory()->create([
        'process_id' => $process->id,
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'MARIA GARCIA',
    ]);

    ProcessSubject::factory()->create([
        'process_id' => $process->id,
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

    ProcessSubject::factory()->create([
        'process_id' => $process->id,
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ',
    ]);

    ProcessSubject::factory()->create([
        'process_id' => $process->id,
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA XYZ S.A.',
    ]);

    ProcessSubject::factory()->create([
        'process_id' => $process->id,
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA ABC S.A.',
    ]);

    ProcessSubject::factory()->create([
        'process_id' => $process->id,
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

    ProcessSubject::factory()->create([
        'process_id' => $process->id,
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

    ProcessSubject::factory()->create([
        'process_id' => $process->id,
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

it('shows correct count indicator for multiple plaintiffs and defendants', function (): void {
    $process = Process::factory()->create();

    // Create 3 plaintiffs
    ProcessSubject::factory()->create([
        'process_id' => $process->id,
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ',
    ]);
    ProcessSubject::factory()->create([
        'process_id' => $process->id,
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'MARIA GARCIA',
    ]);
    ProcessSubject::factory()->create([
        'process_id' => $process->id,
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'CARLOS LOPEZ',
    ]);

    // Create 5 defendants
    ProcessSubject::factory()->create([
        'process_id' => $process->id,
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA XYZ S.A.',
    ]);
    ProcessSubject::factory()->create([
        'process_id' => $process->id,
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA ABC S.A.',
    ]);
    ProcessSubject::factory()->create([
        'process_id' => $process->id,
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA DEF S.A.',
    ]);
    ProcessSubject::factory()->create([
        'process_id' => $process->id,
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA GHI S.A.',
    ]);
    ProcessSubject::factory()->create([
        'process_id' => $process->id,
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

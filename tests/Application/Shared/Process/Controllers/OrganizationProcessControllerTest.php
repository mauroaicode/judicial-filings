<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->user = AppUser::factory()->create([
        'password' => Hash::make('password'),
    ]);
});

it('lists active processes for an organization', function (): void {
    // Process 1: Active in this organization
    $process1 = Process::factory()->create(['process_number' => '11111111111111111111111', 'court' => 'Court A']);
    $this->organization->processes()->attach($process1->id, [
        'is_active' => true,
        'interest_date' => now(),
    ]);

    // Process 2: Inactive in this organization
    $process2 = Process::factory()->create(['process_number' => '22222222222222222222222']);
    $this->organization->processes()->attach($process2->id, [
        'is_active' => false,
        'interest_date' => now(),
    ]);

    // Process 3: Active in another organization
    $process3 = Process::factory()->create();
    $anotherOrg = Organization::factory()->create();
    $anotherOrg->processes()->attach($process3->id, [
        'is_active' => true,
        'interest_date' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/app-user/organizations/{$this->organization->id}/processes");

    $response->assertStatus(200);
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.number', '11111111111111111111111');
    $response->assertJsonPath('0.despacho', 'Court A');
});

it('filters organization processes by number', function (): void {
    $process1 = Process::factory()->create(['process_number' => '12345000000000000000000']);
    $process2 = Process::factory()->create(['process_number' => '67890000000000000000000']);

    $this->organization->processes()->attach([$process1->id, $process2->id], [
        'is_active' => true,
        'interest_date' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/app-user/organizations/{$this->organization->id}/processes?process_number=12345");

    $response->assertStatus(200);
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.id', $process1->id);
});

it('filters organization processes by court', function (): void {
    $process1 = Process::factory()->create(['court' => 'Superior Court']);
    $process2 = Process::factory()->create(['court' => 'Family Court']);

    $this->organization->processes()->attach([$process1->id, $process2->id], [
        'is_active' => true,
        'interest_date' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/app-user/organizations/{$this->organization->id}/processes?court=Superior");

    $response->assertStatus(200);
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.despacho', 'Superior Court');
});

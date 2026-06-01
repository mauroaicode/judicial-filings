<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin-process-status@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create();
    $this->process = Process::factory()->create();
    $this->process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
});

function adminProcessStatusUrl(Process $process, Organization $organization): string
{
    return "/api/admin/processes/{$process->id}/organizations/{$organization->id}/status";
}

it('requires authentication to toggle process status for organization', function (): void {
    $response = $this->patchJson(adminProcessStatusUrl($this->process, $this->organization), [
        'is_active' => false,
    ]);

    $response->assertStatus(401);
});

it('validates that is_active field is required', function (): void {
    $response = $this->actingAs($this->user)
        ->patchJson(adminProcessStatusUrl($this->process, $this->organization), []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['is_active']);
});

it('returns 404 when process is not linked to organization', function (): void {
    $otherOrganization = Organization::factory()->create();

    $response = $this->actingAs($this->user)
        ->patchJson(adminProcessStatusUrl($this->process, $otherOrganization), [
            'is_active' => false,
        ]);

    $response->assertStatus(404);
});

it('deactivates process for organization successfully', function (): void {
    $response = $this->actingAs($this->user)
        ->patchJson(adminProcessStatusUrl($this->process, $this->organization), [
            'is_active' => false,
        ]);

    $response->assertStatus(200);
    $response->assertJson([
        'message' => __('process.deactivated_successfully'),
    ]);

    $this->assertDatabaseHas('organization_processes', [
        'process_id' => $this->process->id,
        'organization_id' => $this->organization->id,
        'is_active' => false,
    ]);
});

it('activates process for organization successfully', function (): void {
    $this->process->organizations()->updateExistingPivot($this->organization->id, [
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson(adminProcessStatusUrl($this->process, $this->organization), [
            'is_active' => true,
        ]);

    $response->assertStatus(200);
    $response->assertJson([
        'message' => __('process.activated_successfully'),
    ]);

    $this->assertDatabaseHas('organization_processes', [
        'process_id' => $this->process->id,
        'organization_id' => $this->organization->id,
        'is_active' => true,
    ]);
});

it('only changes status for the specified organization', function (): void {
    $otherOrganization = Organization::factory()->create();
    $this->process->organizations()->attach($otherOrganization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson(adminProcessStatusUrl($this->process, $this->organization), [
            'is_active' => false,
        ]);

    $response->assertStatus(200);

    $this->assertDatabaseHas('organization_processes', [
        'process_id' => $this->process->id,
        'organization_id' => $this->organization->id,
        'is_active' => false,
    ]);

    $this->assertDatabaseHas('organization_processes', [
        'process_id' => $this->process->id,
        'organization_id' => $otherOrganization->id,
        'is_active' => true,
    ]);
});

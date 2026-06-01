<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin-process-config@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create();
    $this->process = Process::factory()->create([
        'last_activity_date' => Date::now()->subDays(100)->toDateString(),
    ]);

    $this->process->organizations()->attach($this->organization->id, [
        'is_active' => true,
        'interest_date' => now()->toDateString(),
        'lawyer_role' => null,
        'inactivity_alert_level' => null,
    ]);
});

it('requires authentication to list admin process lawyer roles', function (): void {
    $response = $this->getJson('/api/admin/config/processes/roles');

    $response->assertStatus(401);
});

it('requires authentication to assign lawyer role for organization process', function (): void {
    $response = $this->postJson(
        "/api/admin/processes/{$this->process->id}/organizations/{$this->organization->id}/config/roles",
        ['lawyer_role' => 'defendant'],
    );

    $response->assertStatus(401);
});

it('lists lawyer roles when authenticated as admin', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/config/processes/roles');

    $response->assertStatus(200);
    $response->assertJson([
        ['value' => 'plaintiff', 'label' => 'Demandante'],
        ['value' => 'defendant', 'label' => 'Demandado'],
    ]);
});

it('assigns lawyer role for a process and organization and calculates alert level', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson(
            "/api/admin/processes/{$this->process->id}/organizations/{$this->organization->id}/config/roles",
            ['lawyer_role' => 'defendant'],
        );

    $response->assertStatus(200);
    $response->assertJson([
        'message' => __('process.config_updated_successfully'),
    ]);

    $this->assertDatabaseHas('organization_processes', [
        'process_id' => $this->process->id,
        'organization_id' => $this->organization->id,
        'lawyer_role' => 'defendant',
        'inactivity_alert_level' => 'green',
    ]);
});

it('assigns plaintiff role and calculates yellow alert level', function (): void {
    $process = Process::factory()->create([
        'last_activity_date' => Date::now()->subDays(50)->toDateString(),
    ]);
    $process->organizations()->attach($this->organization->id, [
        'is_active' => true,
        'interest_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(
            "/api/admin/processes/{$process->id}/organizations/{$this->organization->id}/config/roles",
            ['lawyer_role' => 'plaintiff'],
        );

    $response->assertStatus(200);

    $this->assertDatabaseHas('organization_processes', [
        'process_id' => $process->id,
        'organization_id' => $this->organization->id,
        'lawyer_role' => 'plaintiff',
        'inactivity_alert_level' => 'yellow',
    ]);
});

it('returns 404 when process is not linked to organization', function (): void {
    $otherOrganization = Organization::factory()->create();

    $response = $this->actingAs($this->user)
        ->postJson(
            "/api/admin/processes/{$this->process->id}/organizations/{$otherOrganization->id}/config/roles",
            ['lawyer_role' => 'plaintiff'],
        );

    $response->assertStatus(404);
});

it('validates lawyer role value', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson(
            "/api/admin/processes/{$this->process->id}/organizations/{$this->organization->id}/config/roles",
            ['lawyer_role' => 'invalid_role'],
        );

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['lawyer_role']);
});

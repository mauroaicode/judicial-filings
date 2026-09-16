<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Organization\Models\OrganizationSetting;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessTimelineEvent;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin-process-organizations@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    config(['organization.defaults.max_active_processes' => null]);

    $this->process = Process::factory()->create();
});

it('requires authentication to attach interested organizations', function (): void {
    $organization = Organization::factory()->create(['is_active' => true]);

    $this->postJson("/api/admin/processes/{$this->process->id}/organizations", [
        'organization_ids' => [$organization->id],
    ])->assertStatus(401);
});

it('attaches one or more interested organizations to a process', function (): void {
    $first = Organization::factory()->create(['name' => 'Org Alpha', 'is_active' => true]);
    $second = Organization::factory()->create(['name' => 'Org Beta', 'is_active' => true]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/admin/processes/{$this->process->id}/organizations", [
            'organization_ids' => [$first->id, $second->id],
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', __('process.organizations_attached_successfully'))
        ->assertJsonPath('organizations.count', 2);

    $ids = collect($response->json('organizations.items'))->pluck('id');
    expect($ids)->toContain($first->id, $second->id);

    $this->assertDatabaseHas('organization_processes', [
        'organization_id' => $first->id,
        'process_id' => $this->process->id,
        'status' => OrganizationProcessStatus::ACTIVE->value,
        'deleted_at' => null,
    ]);

    expect(
        ProcessTimelineEvent::query()
            ->where('process_id', $this->process->id)
            ->where('event_type', ProcessTimelineEventType::TRACKING_ACTIVATED->value)
            ->count()
    )->toBe(2);
});

it('keeps already linked organizations and restores previously trashed links', function (): void {
    $linked = Organization::factory()->create(['is_active' => true]);
    $trashed = Organization::factory()->create(['is_active' => true]);

    $this->process->organizations()->attach($linked->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => OrganizationProcessStatus::ACTIVE->value,
    ]);

    $this->process->organizations()->attach($trashed->id, [
        'interest_date' => now()->subDay()->toDateString(),
        'is_active' => false,
        'status' => OrganizationProcessStatus::INACTIVE->value,
    ]);

    OrganizationProcess::query()
        ->where('organization_id', $trashed->id)
        ->where('process_id', $this->process->id)
        ->first()
        ?->delete();

    $response = $this->actingAs($this->user)
        ->postJson("/api/admin/processes/{$this->process->id}/organizations", [
            'organization_ids' => [$linked->id, $trashed->id],
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('organizations.count', 2);

    expect(
        OrganizationProcess::query()
            ->where('organization_id', $trashed->id)
            ->where('process_id', $this->process->id)
            ->whereNull('deleted_at')
            ->exists()
    )->toBeTrue();
});

it('rejects inactive organizations and missing organization ids', function (): void {
    $inactive = Organization::factory()->create(['is_active' => false]);

    $this->actingAs($this->user)
        ->postJson("/api/admin/processes/{$this->process->id}/organizations", [
            'organization_ids' => [$inactive->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['organization_ids.0']);

    $this->actingAs($this->user)
        ->postJson("/api/admin/processes/{$this->process->id}/organizations", [
            'organization_ids' => ['00000000-0000-0000-0000-000000000000'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['organization_ids.0']);
});

it('rejects attaching when the organization quota is full', function (): void {
    $organization = Organization::factory()->create(['is_active' => true]);
    OrganizationSetting::factory()->create([
        'organization_id' => $organization->id,
        'max_active_processes' => 1,
    ]);

    $existing = Process::factory()->create();
    $existing->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => OrganizationProcessStatus::ACTIVE->value,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/admin/processes/{$this->process->id}/organizations", [
            'organization_ids' => [$organization->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['organization_ids.0']);
});

it('returns 404 when attaching organizations to a missing process', function (): void {
    $organization = Organization::factory()->create(['is_active' => true]);
    $missingId = '00000000-0000-0000-0000-000000000000';

    $this->actingAs($this->user)
        ->postJson("/api/admin/processes/{$missingId}/organizations", [
            'organization_ids' => [$organization->id],
        ])
        ->assertStatus(404);
});

it('requires authentication to remove an interested organization', function (): void {
    $organization = Organization::factory()->create(['is_active' => true]);
    $this->process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => OrganizationProcessStatus::ACTIVE->value,
    ]);

    $this->deleteJson("/api/admin/processes/{$this->process->id}/organizations/{$organization->id}")
        ->assertStatus(401);
});

it('removes an interested organization without affecting the others', function (): void {
    $keep = Organization::factory()->create(['name' => 'Keep Org', 'is_active' => true]);
    $remove = Organization::factory()->create(['name' => 'Remove Org', 'is_active' => true]);

    $this->process->organizations()->attach($keep->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => OrganizationProcessStatus::ACTIVE->value,
    ]);
    $this->process->organizations()->attach($remove->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => OrganizationProcessStatus::ACTIVE->value,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/admin/processes/{$this->process->id}/organizations/{$remove->id}");

    $response->assertStatus(200)
        ->assertJsonPath('message', __('process.moved_to_trash'))
        ->assertJsonPath('organizations.count', 1)
        ->assertJsonPath('organizations.items.0.id', $keep->id);

    expect(
        OrganizationProcess::query()
            ->where('organization_id', $remove->id)
            ->where('process_id', $this->process->id)
            ->exists()
    )->toBeFalse()
        ->and(
            OrganizationProcess::onlyTrashed()
                ->where('organization_id', $remove->id)
                ->where('process_id', $this->process->id)
                ->exists()
        )->toBeTrue()
        ->and(
            OrganizationProcess::query()
                ->where('organization_id', $keep->id)
                ->where('process_id', $this->process->id)
                ->exists()
        )->toBeTrue();
});

it('returns 404 when removing an organization that is not linked to the process', function (): void {
    $organization = Organization::factory()->create(['is_active' => true]);

    $this->actingAs($this->user)
        ->deleteJson("/api/admin/processes/{$this->process->id}/organizations/{$organization->id}")
        ->assertStatus(404);
});

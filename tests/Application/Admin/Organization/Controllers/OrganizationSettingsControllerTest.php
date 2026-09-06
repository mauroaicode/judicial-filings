<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Organization\Models\OrganizationSetting;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    config(['organization.defaults.max_active_processes' => 60]);

    $this->user = User::factory()->create([
        'email' => 'admin-org-settings@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create(['is_active' => true]);
});

it('requires authentication to view organization settings', function (): void {
    $this->getJson("/api/admin/organizations/{$this->organization->id}/settings")
        ->assertUnauthorized();
});

it('returns organization settings for an admin', function (): void {
    OrganizationSetting::factory()->create([
        'organization_id' => $this->organization->id,
        'max_active_processes' => 46,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/admin/organizations/{$this->organization->id}/settings")
        ->assertOk()
        ->assertJsonPath('organization_id', $this->organization->id)
        ->assertJsonPath('max_active_processes', 46)
        ->assertJsonPath('max_active_processes_configured', 46);
});

it('updates organization settings for an admin', function (): void {
    OrganizationSetting::factory()->create([
        'organization_id' => $this->organization->id,
        'max_active_processes' => 10,
    ]);

    $this->actingAs($this->user)
        ->putJson("/api/admin/organizations/{$this->organization->id}/settings", [
            'max_active_processes' => 25,
        ])
        ->assertOk()
        ->assertJsonPath('settings.max_active_processes', 25)
        ->assertJsonPath('settings.max_active_processes_configured', 25);

    expect(
        OrganizationSetting::query()
            ->where('organization_id', $this->organization->id)
            ->value('max_active_processes')
    )->toBe(25);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    Organization::query()->delete();

    $this->user = User::factory()->create([
        'email' => 'admin-org-stats@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);
});

it('requires authentication to get organization stats', function (): void {
    $response = $this->getJson('/api/admin/organizations/stats');

    $response->assertStatus(401);
});

it('returns zero counts when there are no organizations', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/organizations/stats');

    $response->assertOk();
    $response->assertExactJson([
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'natural' => 0,
        'juridical' => 0,
    ]);
});

it('returns organization stats counts', function (): void {
    Organization::factory()->natural()->create([
        'email' => 'stats-n1@example.com',
        'is_active' => true,
    ]);
    Organization::factory()->natural()->create([
        'email' => 'stats-n2@example.com',
        'is_active' => true,
    ]);
    Organization::factory()->natural()->create([
        'email' => 'stats-n3@example.com',
        'is_active' => false,
    ]);
    Organization::factory()->juridical()->create([
        'email' => 'stats-j1@example.com',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/organizations/stats');

    $response->assertOk();
    $response->assertExactJson([
        'total' => 4,
        'active' => 3,
        'inactive' => 1,
        'natural' => 3,
        'juridical' => 1,
    ]);
});

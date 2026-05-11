<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Process\Models\ProcessDataSource;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    ProcessDataSource::query()->where('slug', 'inactive_test_source')->delete();
    ProcessDataSource::query()->create([
        'slug' => 'inactive_test_source',
        'name' => 'Inactive catalog entry',
        'is_active' => false,
    ]);
});

it('requires authentication for admin process data sources index', function (): void {
    $response = $this->getJson('/api/admin/process-data-sources');

    $response->assertStatus(401);
});

it('lists active process data sources for admin', function (): void {
    $user = User::factory()->create([
        'email' => 'admin-pds@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);
    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $user->roles()->attach($adminRole->id);

    $response = $this->actingAs($user)->getJson('/api/admin/process-data-sources');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        '*' => ['id', 'slug', 'name', 'is_active'],
    ]);

    $slugs = collect($response->json())->pluck('slug')->all();
    expect($slugs)->toContain('judicial_branch', 'samai')
        ->and($slugs)->not->toContain('inactive_test_source');

    $rows = collect($response->json());
    expect($rows->every(fn (array $r): bool => $r['is_active'] === true))->toBeTrue();
});

it('requires authentication for app user process data sources index', function (): void {
    $response = $this->getJson('/api/app-user/process-data-sources');

    $response->assertStatus(401);
});

it('lists active process data sources for app user', function (): void {
    $appUser = AppUser::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($appUser)->getJson('/api/app-user/process-data-sources');

    $response->assertStatus(200);
    $slugs = collect($response->json())->pluck('slug')->all();
    expect($slugs)->toContain('judicial_branch', 'samai')
        ->and($slugs)->not->toContain('inactive_test_source');
});

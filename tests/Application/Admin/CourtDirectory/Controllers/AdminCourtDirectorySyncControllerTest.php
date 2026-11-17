<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\CourtDirectory\Enums\CourtDirectorySyncTrigger;
use Src\Domain\CourtDirectory\Models\CourtDirectoryEntry;
use Src\Domain\CourtDirectory\Models\CourtDirectorySyncRun;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;
use Tests\Support\CourtDirectoryReportFake;

beforeEach(function (): void {
    CourtDirectoryEntry::query()->delete();
    CourtDirectorySyncRun::query()->delete();

    $this->user = User::factory()->create([
        'email' => 'admin@court-directory.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);
});

it('requires authentication to sync the court directory', function (): void {
    $this->postJson('/api/admin/court-directory/sync')->assertUnauthorized();
});

it('forbids non-admin users from syncing the court directory', function (): void {
    $plainUser = User::factory()->create([
        'email' => 'plain-court-directory@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $this->actingAs($plainUser)
        ->postJson('/api/admin/court-directory/sync')
        ->assertForbidden();
});

it('syncs the directory when an admin requests it', function (): void {
    CourtDirectoryReportFake::fake([
        [
            'email' => 'despacho001@cendoj.ramajudicial.gov.co',
            'name' => 'Juzgado 001',
            'corporation' => 'Juzgado Municipal',
            'specialty' => 'Civil',
            'department' => 'Valle',
            'city' => 'Cali',
            'account_type' => 'Área',
            'desk_code' => '760014189001',
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/court-directory/sync');

    $response->assertOk()
        ->assertJsonPath('trigger', CourtDirectorySyncTrigger::Admin->value)
        ->assertJsonPath('status', 'succeeded')
        ->assertJsonPath('rows_upserted', 1);

    expect(CourtDirectoryEntry::query()->where('email', 'despacho001@cendoj.ramajudicial.gov.co')->exists())->toBeTrue();

    $this->actingAs($this->user)
        ->getJson('/api/admin/court-directory/runs')
        ->assertOk()
        ->assertJsonPath('data.0.trigger', CourtDirectorySyncTrigger::Admin->value);
});

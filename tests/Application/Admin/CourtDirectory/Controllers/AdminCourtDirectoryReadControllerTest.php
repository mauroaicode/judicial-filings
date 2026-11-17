<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\CourtDirectory\Models\CourtDirectoryEntry;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    CourtDirectoryEntry::query()->delete();

    $this->user = User::factory()->create([
        'email' => 'admin-directory-read@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);
});

it('requires an admin to read the court directory', function (): void {
    $this->getJson('/api/admin/court-directory/by-desk-code/760014189007')
        ->assertUnauthorized();
});

it('returns desk emails to an admin', function (): void {
    CourtDirectoryEntry::factory()->create([
        'email' => 'j07pccmcali@cendoj.ramajudicial.gov.co',
        'desk_code' => '760014189007',
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/admin/court-directory/by-desk-code/760014189007')
        ->assertOk()
        ->assertJsonPath('data.0.email', 'j07pccmcali@cendoj.ramajudicial.gov.co');
});

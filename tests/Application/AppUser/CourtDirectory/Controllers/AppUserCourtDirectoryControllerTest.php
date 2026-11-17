<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\CourtDirectory\Models\CourtDirectoryEntry;
use Src\Domain\Organization\Models\Organization;

beforeEach(function (): void {
    CourtDirectoryEntry::query()->delete();

    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create([
        'email' => 'lawyer-directory@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);
    $this->appUser->organizations()->attach($this->organization->id, [
        'is_owner' => true,
    ]);
});

it('requires authentication to read the court directory', function (): void {
    $this->getJson('/api/app-user/court-directory/by-desk-code/760014189007')
        ->assertUnauthorized();
});

it('returns every email for a desk code, including inactive ones', function (): void {
    CourtDirectoryEntry::factory()->create([
        'email' => 'j07pccmc@cendoj.ramajudicial.gov.co',
        'name' => 'Juzgado 007 de Pequeñas Causas de Cali',
        'desk_code' => '760014189007',
        'city' => 'Cali',
        'department' => 'Valle',
        'specialty' => 'Pequeñas Causas',
        'is_active' => true,
    ]);
    CourtDirectoryEntry::factory()->create([
        'email' => 'archivoj07@cendoj.ramajudicial.gov.co',
        'desk_code' => '760014189007',
        'is_active' => false,
    ]);
    CourtDirectoryEntry::factory()->create([
        'email' => 'otro@cendoj.ramajudicial.gov.co',
        'desk_code' => '760014189001',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/court-directory/by-desk-code/76-001-41-89-007');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.email', 'j07pccmc@cendoj.ramajudicial.gov.co')
        ->assertJsonPath('data.0.desk_code', '760014189007')
        ->assertJsonPath('data.1.is_active', false);
});

it('searches active desks and hides inactive ones', function (): void {
    CourtDirectoryEntry::factory()->create([
        'email' => 'activo@cendoj.ramajudicial.gov.co',
        'name' => 'Juzgado Civil de Cali',
        'city' => 'Cali',
        'is_active' => true,
    ]);
    CourtDirectoryEntry::factory()->create([
        'email' => 'inactivo@cendoj.ramajudicial.gov.co',
        'name' => 'Juzgado Civil de Cali archivado',
        'city' => 'Cali',
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/court-directory?q=Cali');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'activo@cendoj.ramajudicial.gov.co');
});

it('rejects a desk code that is not 12 digits', function (): void {
    $this->actingAs($this->appUser)
        ->getJson('/api/app-user/court-directory/by-desk-code/12345')
        ->assertNotFound();
});

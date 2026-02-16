<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->where('guard_name', 'admin')->first();
    if ($adminRole) {
        $this->user->roles()->attach($adminRole->id);
    }
});

it('requires authentication to list organization types', function (): void {
    $response = $this->getJson('/api/admin/organization-types');

    $response->assertStatus(401);
});

it('lists organization types when authenticated as admin', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/organization-types');

    $response->assertStatus(200);
    $response->assertJsonStructure(['data' => [['value', 'label']]]);
    $data = $response->json('data');
    expect($data)->toHaveCount(2);
    $values = array_column($data, 'value');
    expect($values)->toContain('natural', 'juridical');
});

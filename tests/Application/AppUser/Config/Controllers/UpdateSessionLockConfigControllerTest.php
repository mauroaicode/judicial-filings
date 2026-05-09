<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\Config\Controllers;

use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create([
        'session_lock_enabled' => true,
        'session_lock_timeout' => 5,
    ]);
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);
});

it('can update user config for session lock', function (): void {
    $data = [
        'session_lock_enabled' => false,
        'session_lock_timeout' => 10,
    ];

    $response = $this->actingAs($this->appUser, 'sanctum')
        ->putJson('/api/app-user/config/session-lock', $data);

    $response->assertStatus(200);
    $response->assertJson([
        'id' => $this->appUser->id,
        'session_lock_enabled' => false,
        'session_lock_timeout' => 10,
    ]);

    $this->appUser->refresh();
    expect((bool) $this->appUser->session_lock_enabled)->toBeFalse();
    expect((int) $this->appUser->session_lock_timeout)->toBe(10);
});

it('fails to update config with invalid timeout', function (): void {
    $data = [
        'session_lock_enabled' => true,
        'session_lock_timeout' => 0, // Must be min 1
    ];

    $response = $this->actingAs($this->appUser, 'sanctum')
        ->putJson('/api/app-user/config/session-lock', $data);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['session_lock_timeout']);
});

it('fails to update config missing fields', function (): void {
    $data = [];

    $response = $this->actingAs($this->appUser, 'sanctum')
        ->putJson('/api/app-user/config/session-lock', $data);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['session_lock_enabled', 'session_lock_timeout']);
});

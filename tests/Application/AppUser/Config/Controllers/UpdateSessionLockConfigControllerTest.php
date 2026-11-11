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
    expect($this->appUser->session_unlock_method->value)->toBe('password');
});

it('saves otp as the screen unlock method when 2fa is enabled', function (): void {
    $this->appUser->update([
        'google_2fa_secret' => 'TESTSECRET123456',
        'google_2fa_enabled' => true,
    ]);

    $token = $this->appUser->createToken('auth-token', ['2fa_verified'])->plainTextToken;

    $response = $this->withToken($token)
        ->putJson('/api/app-user/config/session-lock', [
            'session_lock_enabled' => true,
            'session_lock_timeout' => 5,
            'session_unlock_method' => 'otp',
        ]);

    $response->assertOk()
        ->assertJsonPath('session_unlock_method', 'otp');

    $this->appUser->refresh();
    expect($this->appUser->session_unlock_method->value)->toBe('otp');
});

it('rejects otp unlock when 2fa is not enabled', function (): void {
    $response = $this->actingAs($this->appUser, 'sanctum')
        ->putJson('/api/app-user/config/session-lock', [
            'session_lock_enabled' => true,
            'session_lock_timeout' => 5,
            'session_unlock_method' => 'otp',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['session_unlock_method']);
});

it('rejects an unknown unlock method', function (): void {
    $response = $this->actingAs($this->appUser, 'sanctum')
        ->putJson('/api/app-user/config/session-lock', [
            'session_lock_enabled' => true,
            'session_lock_timeout' => 5,
            'session_unlock_method' => 'pin',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['session_unlock_method']);
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

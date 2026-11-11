<?php

declare(strict_types=1);

use PragmaRX\Google2FA\Google2FA;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create(['is_active' => true]);
    $this->google2fa = new Google2FA;
    $this->secret = $this->google2fa->generateSecretKey();
    $this->appUser = AppUser::factory()->create([
        'email' => 'otp-lock@example.com',
        'identification' => '5647382910',
        'password' => 'password1234',
        'email_verified_at' => now(),
        'google_2fa_secret' => $this->secret,
        'google_2fa_enabled' => true,
        'session_unlock_method' => 'otp',
    ]);
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);
    $this->token = $this->appUser->createToken('auth-token', ['2fa_verified'])->plainTextToken;
});

it('unlocks the screen with a valid authenticator code without replacing the token', function (): void {
    $response = $this
        ->withToken($this->token)
        ->postJson('/api/app-user/verify-otp', [
            'one_time_password' => $this->google2fa->getCurrentOtp($this->secret),
        ]);

    $response->assertOk()
        ->assertJson([
            'message' => __('auth.session_unlocked'),
        ]);

    expect($this->appUser->tokens()->count())->toBe(1);

    $this->app['auth']->forgetGuards();

    $this->withToken($this->token)
        ->getJson('/api/app-user/profile')
        ->assertOk();
});

it('still unlocks the screen with the password when otp is the preferred method', function (): void {
    $this->withToken($this->token)
        ->postJson('/api/app-user/verify-password', [
            'password' => 'password1234',
        ])
        ->assertOk()
        ->assertJson([
            'message' => __('auth.session_unlocked'),
        ]);
});

it('rejects an invalid authenticator code', function (): void {
    $this->withToken($this->token)
        ->postJson('/api/app-user/verify-otp', [
            'one_time_password' => '000000',
        ])
        ->assertStatus(422)
        ->assertJson([
            'code' => 422,
            'messages' => [__('auth.invalid_otp')],
        ]);
});

it('rejects screen unlock with otp when 2fa is disabled', function (): void {
    $this->appUser->update([
        'google_2fa_enabled' => false,
        'google_2fa_secret' => null,
        'session_unlock_method' => 'password',
    ]);

    $token = $this->appUser->createToken('auth-token')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/app-user/verify-otp', [
            'one_time_password' => '123456',
        ])
        ->assertStatus(400)
        ->assertJson([
            'code' => 400,
            'messages' => [__('auth.no_2fa_setup')],
        ]);
});

it('requires authentication to unlock with otp', function (): void {
    $this->postJson('/api/app-user/verify-otp', [
        'one_time_password' => '123456',
    ])->assertUnauthorized();
});

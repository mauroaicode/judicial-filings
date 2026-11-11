<?php

declare(strict_types=1);

use PragmaRX\Google2FA\Google2FA;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create(['is_active' => true]);
    $this->appUser = AppUser::factory()->create([
        'email' => 'twofactor@example.com',
        'identification' => '1098765432',
        'password' => 'password1234',
        'email_verified_at' => now(),
    ]);
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);
    $this->google2fa = new Google2FA;
});

it('requires authentication to generate a 2fa code', function (): void {
    $this->getJson('/api/app-user/2fa/generate')->assertUnauthorized();
});

it('generates a secret and qr code without enabling 2fa', function (): void {
    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/2fa/generate');

    $response->assertOk()
        ->assertJsonStructure(['secret', 'qr_code_url', 'qr_code_svg']);

    expect($response->json('qr_code_url'))->toStartWith('otpauth://totp/')
        ->and($response->json('qr_code_svg'))->toContain('svg')
        ->and($response->json('secret'))->not->toBeEmpty();

    $this->appUser->refresh();

    expect($this->appUser->google_2fa_secret)->toBe($response->json('secret'))
        ->and($this->appUser->google_2fa_enabled)->toBeFalse();
});

it('verifies a valid code, enables 2fa and returns a token that can access the api', function (): void {
    $secret = $this->google2fa->generateSecretKey();
    $this->appUser->update([
        'google_2fa_secret' => $secret,
        'google_2fa_enabled' => false,
    ]);

    $setupToken = $this->appUser->createToken('setup')->plainTextToken;

    $response = $this->withToken($setupToken)
        ->postJson('/api/app-user/2fa/verify', [
            'one_time_password' => $this->google2fa->getCurrentOtp($secret),
        ]);

    $response->assertOk()->assertJsonStructure(['token']);

    $this->appUser->refresh();
    expect($this->appUser->google_2fa_enabled)->toBeTrue();

    $this->app['auth']->forgetGuards();

    $this->withToken($response->json('token'))
        ->getJson('/api/app-user/profile')
        ->assertOk()
        ->assertJsonPath('google_2fa_enabled', true);
});

it('rejects an invalid code when activating 2fa', function (): void {
    $this->appUser->update([
        'google_2fa_secret' => $this->google2fa->generateSecretKey(),
        'google_2fa_enabled' => false,
    ]);

    $this->actingAs($this->appUser)
        ->postJson('/api/app-user/2fa/verify', [
            'one_time_password' => '000000',
        ])
        ->assertStatus(422)
        ->assertJson([
            'code' => 422,
            'messages' => [__('auth.invalid_otp')],
        ]);

    $this->appUser->refresh();
    expect($this->appUser->google_2fa_enabled)->toBeFalse();
});

it('rejects verification when 2fa was not generated', function (): void {
    $this->actingAs($this->appUser)
        ->postJson('/api/app-user/2fa/verify', [
            'one_time_password' => '123456',
        ])
        ->assertStatus(400)
        ->assertJson([
            'code' => 400,
            'messages' => [__('auth.no_2fa_setup')],
        ]);
});

it('requires the one time password to verify 2fa', function (): void {
    $this->actingAs($this->appUser)
        ->postJson('/api/app-user/2fa/verify', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['one_time_password']);
});

it('disables 2fa and returns a token that can access the api', function (): void {
    $this->appUser->update([
        'google_2fa_secret' => $this->google2fa->generateSecretKey(),
        'google_2fa_enabled' => true,
        'session_unlock_method' => 'otp',
    ]);

    $setupToken = $this->appUser->createToken('setup')->plainTextToken;

    $response = $this->withToken($setupToken)
        ->patchJson('/api/app-user/2fa/disable');

    $response->assertOk()->assertJsonStructure(['token']);

    $this->appUser->refresh();
    expect($this->appUser->google_2fa_enabled)->toBeFalse()
        ->and($this->appUser->google_2fa_secret)->toBeNull()
        ->and($this->appUser->session_unlock_method->value)->toBe('password');

    $this->app['auth']->forgetGuards();

    $this->withToken($response->json('token'))
        ->getJson('/api/app-user/profile')
        ->assertOk()
        ->assertJsonPath('google_2fa_enabled', false);
});

it('rejects disable when 2fa is not enabled', function (): void {
    $this->actingAs($this->appUser)
        ->patchJson('/api/app-user/2fa/disable')
        ->assertStatus(400)
        ->assertJson([
            'code' => 400,
            'messages' => [__('auth.2fa_not_enabled')],
        ]);
});

it('exchanges the login token for a verified token after a valid code', function (): void {
    $secret = $this->google2fa->generateSecretKey();
    $this->appUser->update([
        'google_2fa_secret' => $secret,
        'google_2fa_enabled' => true,
    ]);

    $login = $this->postJson('/api/app-user/login', [
        'identification' => '1098765432',
        'password' => 'password1234',
    ]);

    $login->assertOk();
    expect($login->json('requires_2fa'))->toBeTrue()
        ->and($login->json('user.google_2fa_enabled'))->toBeTrue();

    $this->withToken($login->json('token'))
        ->getJson('/api/app-user/profile')
        ->assertForbidden()
        ->assertJson([
            'code' => 403,
            'messages' => [__('auth.2fa_required')],
        ]);

    $validated = $this->withToken($login->json('token'))
        ->postJson('/api/app-user/2fa/validate', [
            'one_time_password' => $this->google2fa->getCurrentOtp($secret),
        ]);

    $validated->assertOk()->assertJsonStructure(['token']);

    $this->app['auth']->forgetGuards();

    $this->withToken($validated->json('token'))
        ->getJson('/api/app-user/profile')
        ->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withToken($login->json('token'))
        ->getJson('/api/app-user/profile')
        ->assertUnauthorized();
});

it('rejects an invalid code when validating 2fa after login', function (): void {
    $this->appUser->update([
        'google_2fa_secret' => $this->google2fa->generateSecretKey(),
        'google_2fa_enabled' => true,
    ]);

    $this->actingAs($this->appUser)
        ->postJson('/api/app-user/2fa/validate', [
            'one_time_password' => '000000',
        ])
        ->assertStatus(422)
        ->assertJson([
            'code' => 422,
            'messages' => [__('auth.invalid_otp')],
        ]);
});

it('rejects validation when 2fa is not enabled', function (): void {
    $this->actingAs($this->appUser)
        ->postJson('/api/app-user/2fa/validate', [
            'one_time_password' => '123456',
        ])
        ->assertStatus(400)
        ->assertJson([
            'code' => 400,
            'messages' => [__('auth.no_2fa_setup')],
        ]);
});

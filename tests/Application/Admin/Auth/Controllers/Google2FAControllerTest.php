<?php

declare(strict_types=1);

use PragmaRX\Google2FA\Google2FA;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin-2fa@example.com',
        'password' => 'password1234',
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);
    $this->google2fa = new Google2FA;
});

it('requires authentication to generate a 2fa code', function (): void {
    $this->getJson('/api/admin/2fa/generate')->assertUnauthorized();
});

it('generates a secret and qr code without enabling 2fa', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/2fa/generate');

    $response->assertOk()
        ->assertJsonStructure(['secret', 'qr_code_url', 'qr_code_svg']);

    expect($response->json('qr_code_url'))->toStartWith('otpauth://totp/')
        ->and($response->json('qr_code_svg'))->toContain('svg');

    $this->user->refresh();

    expect($this->user->google_2fa_secret)->toBe($response->json('secret'))
        ->and($this->user->google_2fa_enabled)->toBeFalse();
});

it('verifies a valid code and unlocks the admin api', function (): void {
    $secret = $this->google2fa->generateSecretKey();
    $this->user->update([
        'google_2fa_secret' => $secret,
        'google_2fa_enabled' => false,
    ]);

    $setupToken = $this->user->createToken('setup')->plainTextToken;

    $response = $this->withToken($setupToken)
        ->postJson('/api/admin/2fa/verify', [
            'one_time_password' => $this->google2fa->getCurrentOtp($secret),
        ]);

    $response->assertOk()->assertJsonStructure(['token']);

    $this->user->refresh();
    expect($this->user->google_2fa_enabled)->toBeTrue();

    $this->app['auth']->forgetGuards();

    $this->withToken($response->json('token'))
        ->getJson('/api/admin/process-data-sources')
        ->assertOk();
});

it('rejects an invalid code when activating admin 2fa', function (): void {
    $this->user->update([
        'google_2fa_secret' => $this->google2fa->generateSecretKey(),
        'google_2fa_enabled' => false,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/admin/2fa/verify', [
            'one_time_password' => '000000',
        ])
        ->assertStatus(422)
        ->assertJson([
            'code' => 422,
            'messages' => [__('auth.invalid_otp')],
        ]);
});

it('disables admin 2fa', function (): void {
    $this->user->update([
        'google_2fa_secret' => $this->google2fa->generateSecretKey(),
        'google_2fa_enabled' => true,
    ]);

    $setupToken = $this->user->createToken('setup')->plainTextToken;

    $response = $this->withToken($setupToken)
        ->patchJson('/api/admin/2fa/disable');

    $response->assertOk()->assertJsonStructure(['token']);

    $this->user->refresh();
    expect($this->user->google_2fa_enabled)->toBeFalse()
        ->and($this->user->google_2fa_secret)->toBeNull();

    $this->app['auth']->forgetGuards();

    $this->withToken($response->json('token'))
        ->getJson('/api/admin/process-data-sources')
        ->assertOk();
});

it('rejects disable when admin 2fa is not enabled', function (): void {
    $this->actingAs($this->user)
        ->patchJson('/api/admin/2fa/disable')
        ->assertStatus(400)
        ->assertJson([
            'code' => 400,
            'messages' => [__('auth.2fa_not_enabled')],
        ]);
});

it('blocks admin routes until the login code is validated', function (): void {
    $secret = $this->google2fa->generateSecretKey();
    $this->user->update([
        'google_2fa_secret' => $secret,
        'google_2fa_enabled' => true,
    ]);

    $login = $this->postJson('/api/admin/login', [
        'email' => 'admin-2fa@example.com',
        'password' => 'password1234',
    ]);

    $login->assertOk();
    expect($login->json('requires_2fa'))->toBeTrue()
        ->and($login->json('user.google_2fa_enabled'))->toBeTrue();

    $this->withToken($login->json('token'))
        ->getJson('/api/admin/process-data-sources')
        ->assertForbidden()
        ->assertJson([
            'code' => 403,
            'messages' => [__('auth.2fa_required')],
        ]);

    $validated = $this->withToken($login->json('token'))
        ->postJson('/api/admin/2fa/validate', [
            'one_time_password' => $this->google2fa->getCurrentOtp($secret),
        ]);

    $validated->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withToken($validated->json('token'))
        ->getJson('/api/admin/process-data-sources')
        ->assertOk();
});

it('rejects validation when admin 2fa is not configured', function (): void {
    $this->actingAs($this->user)
        ->postJson('/api/admin/2fa/validate', [
            'one_time_password' => '123456',
        ])
        ->assertStatus(400)
        ->assertJson([
            'code' => 400,
            'messages' => [__('auth.no_2fa_setup')],
        ]);
});

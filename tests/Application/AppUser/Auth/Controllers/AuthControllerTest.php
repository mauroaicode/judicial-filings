<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create(['is_active' => true]);
    $this->appUser = AppUser::factory()->create([
        'email' => 'test@example.com',
        'identification' => '1234567890',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);
});

it('allows user to login with valid credentials', function (): void {
    $response = $this->postJson('/api/app-user/login', [
        'identification' => '1234567890',
        'password' => 'password1234',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'token',
        'requires_2fa',
        'is_first_login',
    ]);
    expect($response->json('token'))->not->toBeNull();
});

it('rejects login with invalid identification', function (): void {
    $response = $this->postJson('/api/app-user/login', [
        'identification' => 'invalid-identification',
        'password' => 'password1234',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('auth.failed')],
    ]);
});

it('rejects login with invalid password', function (): void {
    $response = $this->postJson('/api/app-user/login', [
        'identification' => '1234567890',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('auth.failed')],
    ]);
});

it('rejects login with unverified email', function (): void {
    $this->appUser->update(['email_verified_at' => null]);

    $response = $this->postJson('/api/app-user/login', [
        'identification' => '1234567890',
        'password' => 'password1234',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('auth.email_not_verified')],
    ]);
});

it('rejects login when organization is inactive', function (): void {
    $this->organization->update(['is_active' => false]);

    $response = $this->postJson('/api/app-user/login', [
        'identification' => '1234567890',
        'password' => 'password1234',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('auth.user_inactive')],
    ]);
});

it('blocks authenticated app user when organization becomes inactive', function (): void {
    $loginResponse = $this->postJson('/api/app-user/login', [
        'identification' => '1234567890',
        'password' => 'password1234',
    ]);

    $token = $loginResponse->json('token');
    $this->organization->update(['is_active' => false]);

    $response = $this->withToken($token)
        ->getJson('/api/app-user/dashboard/stats');

    $response->assertStatus(401);
    $response->assertJson([
        'messages' => [__('auth.user_inactive')],
    ]);
});

it('requires identification field', function (): void {
    $response = $this->postJson('/api/app-user/login', [
        'password' => 'password1234',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['identification']);
});

it('requires password field', function (): void {
    $response = $this->postJson('/api/app-user/login', [
        'identification' => '1234567890',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

it('requires string format for identification', function (): void {
    $response = $this->postJson('/api/app-user/login', [
        'identification' => ['array'],
        'password' => 'password1234',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['identification']);
});

it('returns requires_2fa as false when 2fa is not enabled', function (): void {
    $response = $this->postJson('/api/app-user/login', [
        'identification' => '1234567890',
        'password' => 'password1234',
    ]);

    $response->assertStatus(200);
    expect($response->json('requires_2fa'))->toBeFalse();
});

it('returns is_first_login as false by default', function (): void {
    $response = $this->postJson('/api/app-user/login', [
        'identification' => '1234567890',
        'password' => 'password1234',
    ]);

    $response->assertStatus(200);
    expect($response->json('is_first_login'))->toBeFalse();
});

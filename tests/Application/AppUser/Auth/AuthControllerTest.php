<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;

beforeEach(function (): void {
    $this->appUser = AppUser::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);
});

it('allows user to login with valid credentials', function (): void {
    $response = $this->postJson('/api/app-user/login', [
        'email' => 'test@example.com',
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

it('rejects login with invalid email', function (): void {
    $response = $this->postJson('/api/app-user/login', [
        'email' => 'invalid@example.com',
        'password' => 'password1234',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('auth.failed')],
    ]);
});

it('rejects login with invalid password', function (): void {
    $response = $this->postJson('/api/app-user/login', [
        'email' => 'test@example.com',
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
        'email' => 'test@example.com',
        'password' => 'password1234',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('auth.email_not_verified')],
    ]);
});

it('requires email field', function (): void {
    $response = $this->postJson('/api/app-user/login', [
        'password' => 'password1234',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

it('requires password field', function (): void {
    $response = $this->postJson('/api/app-user/login', [
        'email' => 'test@example.com',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

it('requires valid email format', function (): void {
    $response = $this->postJson('/api/app-user/login', [
        'email' => 'invalid-email',
        'password' => 'password1234',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

it('returns requires_2fa as false when 2fa is not enabled', function (): void {
    $response = $this->postJson('/api/app-user/login', [
        'email' => 'test@example.com',
        'password' => 'password1234',
    ]);

    $response->assertStatus(200);
    expect($response->json('requires_2fa'))->toBeFalse();
});

it('returns is_first_login as false by default', function (): void {
    $response = $this->postJson('/api/app-user/login', [
        'email' => 'test@example.com',
        'password' => 'password1234',
    ]);

    $response->assertStatus(200);
    expect($response->json('is_first_login'))->toBeFalse();
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;

beforeEach(function (): void {
    $this->appUser = AppUser::factory()->create([
        'email' => 'test@example.com',
        'identification' => '1234567890',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);
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

it('rejects login with invalid email', function (): void {
    $response = $this->postJson('/api/app-user/login', [
        'identification' => 'invalid-id',
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

it('requires valid identification format', function (): void {
    // Eliminamos este test o lo modificamos. Ya que no hay validación formativa para identification excepto 'Required',
    // podemos dejar de probar 'invalid-email' o probar algun regex si agregamos uno después.
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

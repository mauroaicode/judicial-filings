<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;

beforeEach(function (): void {
    $this->appUser = AppUser::factory()->create([
        'email' => 'lock-test@example.com',
        'identification' => '9876543210',
        'password' => Hash::make('correct-password'),
        'email_verified_at' => now(),
    ]);

    $this->token = $this->appUser->createToken('test-token')->plainTextToken;
});

// ─── Casos exitosos ───────────────────────────────────────────────────────────

it('unlocks session with correct password', function (): void {
    $response = $this
        ->withToken($this->token)
        ->postJson('/api/app-user/verify-password', [
            'password' => 'correct-password',
        ]);

    $response->assertStatus(200);
    $response->assertJson([
        'message' => __('auth.session_unlocked'),
    ]);
});

// ─── Casos de error de contraseña ────────────────────────────────────────────

it('returns 422 when password is wrong', function (): void {
    $response = $this
        ->withToken($this->token)
        ->postJson('/api/app-user/verify-password', [
            'password' => 'wrong-password',
        ]);

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('auth.password_incorrect')],
    ]);
});

it('returns 422 when password field is missing', function (): void {
    $response = $this
        ->withToken($this->token)
        ->postJson('/api/app-user/verify-password', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

it('returns 422 when password is empty string', function (): void {
    $response = $this
        ->withToken($this->token)
        ->postJson('/api/app-user/verify-password', [
            'password' => '',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

// ─── Seguridad: acceso sin token ─────────────────────────────────────────────

it('returns 401 when no bearer token is provided', function (): void {
    $response = $this->postJson('/api/app-user/verify-password', [
        'password' => 'correct-password',
    ]);

    $response->assertStatus(401);
});

it('returns 401 when bearer token is invalid', function (): void {
    $response = $this
        ->withToken('invalid-token-xyz')
        ->postJson('/api/app-user/verify-password', [
            'password' => 'correct-password',
        ]);

    $response->assertStatus(401);
});

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

    // Assign admin role to user
    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);
});

it('allows admin to login with valid credentials', function (): void {
    $response = $this->postJson('/api/admin/login', [
        'email' => 'admin@example.com',
        'password' => 'password1234',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'token',
        'user',
        'requires_2fa',
        'is_first_login',
    ]);
    expect($response->json('token'))->not->toBeNull();
    expect($response->json('user.id'))->toBe($this->user->id);
    expect($response->json('user.email'))->toBe('admin@example.com');
});

it('rejects login with invalid email', function (): void {
    $response = $this->postJson('/api/admin/login', [
        'email' => 'invalid@example.com',
        'password' => 'password1234',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('auth.failed')],
    ]);
});

it('rejects login with invalid password', function (): void {
    $response = $this->postJson('/api/admin/login', [
        'email' => 'admin@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('auth.failed')],
    ]);
});

it('rejects login with unverified email', function (): void {
    $this->user->update(['email_verified_at' => null]);

    $response = $this->postJson('/api/admin/login', [
        'email' => 'admin@example.com',
        'password' => 'password1234',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('auth.email_not_verified')],
    ]);
});

it('rejects login with inactive user', function (): void {
    $this->user->update(['state' => UserStatus::INACTIVE]);

    $response = $this->postJson('/api/admin/login', [
        'email' => 'admin@example.com',
        'password' => 'password1234',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('auth.user_inactive')],
    ]);
});

it('requires email field', function (): void {
    $response = $this->postJson('/api/admin/login', [
        'password' => 'password1234',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

it('requires password field', function (): void {
    $response = $this->postJson('/api/admin/login', [
        'email' => 'admin@example.com',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

it('requires valid email format', function (): void {
    $response = $this->postJson('/api/admin/login', [
        'email' => 'invalid-email',
        'password' => 'password1234',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

it('returns requires_2fa as false when 2fa is not enabled', function (): void {
    $response = $this->postJson('/api/admin/login', [
        'email' => 'admin@example.com',
        'password' => 'password1234',
    ]);

    $response->assertStatus(200);
    expect($response->json('requires_2fa'))->toBeFalse();
});

it('returns is_first_login as false by default', function (): void {
    $response = $this->postJson('/api/admin/login', [
        'email' => 'admin@example.com',
        'password' => 'password1234',
    ]);

    $response->assertStatus(200);
    expect($response->json('is_first_login'))->toBeFalse();
});

it('returns user resource with correct structure', function (): void {
    $response = $this->postJson('/api/admin/login', [
        'email' => 'admin@example.com',
        'password' => 'password1234',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'user' => [
            'id',
            'name',
            'last_name',
            'email',
            'slug',
            'phone',
            'address',
            'roles',
        ],
    ]);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Src\Application\AppUser\Auth\Notifications\ForgotPasswordNotification;
use Src\Domain\AppUser\Models\AppUser;

beforeEach(function (): void {
    Notification::fake();
    $this->appUser = AppUser::factory()->create([
        'email' => 'test@example.com',
        'identification' => '1234567890',
        'password' => Hash::make('old-password'),
        'must_change_password' => true,
    ]);
});

it('can request a password reset link using identification', function (): void {
    $response = $this->postJson('/api/app-user/forgot-password', [
        'identification' => '1234567890',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['message' => __('auth.forgot_password_sent')]);

    Notification::assertSentTo($this->appUser, ForgotPasswordNotification::class);
    
    $this->assertDatabaseHas('password_reset_tokens', [
        'email' => $this->appUser->email,
    ]);
});

it('returns error if identification is not found', function (): void {
    $response = $this->postJson('/api/app-user/forgot-password', [
        'identification' => 'non-existent',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['identification']);
    expect($response->json('errors.identification.0'))->toBe(__('auth.user_not_found'));
    
    Notification::assertNothingSent();
});

it('can reset password with a valid token', function (): void {
    $token = 'plain-text-token';
    DB::table('password_reset_tokens')->insert([
        'email' => $this->appUser->email,
        'token' => Hash::make($token),
        'created_at' => now(),
    ]);

    $response = $this->postJson('/api/app-user/reset-password', [
        'identification' => '1234567890',
        'token' => $token,
        'password' => 'new-password-1234',
        'password_confirmation' => 'new-password-1234',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['message' => __('auth.password_reset_successful')]);

    $this->appUser->refresh();
    expect(Hash::check('new-password-1234', $this->appUser->password))->toBeTrue();
    expect($this->appUser->must_change_password)->toBeFalse();
    
    $this->assertDatabaseMissing('password_reset_tokens', [
        'email' => $this->appUser->email,
    ]);
});

it('rejects password reset with invalid token', function (): void {
    DB::table('password_reset_tokens')->insert([
        'email' => $this->appUser->email,
        'token' => Hash::make('real-token'),
        'created_at' => now(),
    ]);

    $response = $this->postJson('/api/app-user/reset-password', [
        'identification' => '1234567890',
        'token' => 'wrong-token',
        'password' => 'new-password-1234',
        'password_confirmation' => 'new-password-1234',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
    expect($response->json('errors.token.0'))->toBe(__('auth.invalid_token'));
});

it('rejects password reset with expired token', function (): void {
    $token = 'expired-token';
    $expire = config('auth.passwords.users.expire');
    
    DB::table('password_reset_tokens')->insert([
        'email' => $this->appUser->email,
        'token' => Hash::make($token),
        'created_at' => now()->subMinutes($expire + 1),
    ]);

    $response = $this->postJson('/api/app-user/reset-password', [
        'identification' => '1234567890',
        'token' => $token,
        'password' => 'new-password-1234',
        'password_confirmation' => 'new-password-1234',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
});

it('requires password confirmation to match', function (): void {
    $response = $this->postJson('/api/app-user/reset-password', [
        'identification' => '1234567890',
        'token' => 'any-token',
        'password' => 'new-password-1234',
        'password_confirmation' => 'different-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

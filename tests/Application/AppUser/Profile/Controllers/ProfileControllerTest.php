<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\Profile\Controllers;

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create([
        'name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'identification' => '123456789',
        'password' => Hash::make('password123'),
    ]);
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);
});

it('can show user profile', function (): void {
    $response = $this->actingAs($this->appUser, 'sanctum')
        ->getJson('/api/app-user/profile');

    $response->assertStatus(200);
    $response->assertJson([
        'id' => $this->appUser->id,
        'name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
    ]);
});

it('can update user profile', function (): void {
    $email = fake()->unique()->safeEmail();
    $identification = fake()->unique()->numerify('##########');

    $data = [
        'name' => 'Carlos',
        'last_name' => 'López',
        'email' => $email,
        'identification' => $identification,
    ];

    $response = $this->actingAs($this->appUser, 'sanctum')
        ->putJson('/api/app-user/profile', $data);

    $response->assertStatus(200);
    $response->assertJson([
        'name' => 'Carlos',
        'last_name' => 'López',
        'email' => $email,
        'slug' => 'carlos-lopez',
    ]);

    $this->appUser->refresh();
    expect($this->appUser->name)->toBe('Carlos');
    expect($this->appUser->last_name)->toBe('López');
    expect($this->appUser->identification)->toBe($identification);
});

it('can update user password', function (): void {
    $data = [
        'name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'identification' => '123456789',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ];

    $response = $this->actingAs($this->appUser, 'sanctum')
        ->putJson('/api/app-user/profile', $data);

    $response->assertStatus(200);

    $this->appUser->refresh();
    expect(Hash::check('newpassword123', $this->appUser->password))->toBeTrue();
});

it('fails to update profile with existing email', function (): void {
    $otherUser = AppUser::factory()->create(['email' => 'other@example.com']);

    $data = [
        'name' => 'Carlos',
        'last_name' => 'López',
        'email' => 'other@example.com',
        'identification' => '98765432-1',
    ];

    $response = $this->actingAs($this->appUser, 'sanctum')
        ->putJson('/api/app-user/profile', $data);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

it('fails to update profile when password confirmation does not match', function (): void {
    $data = [
        'name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'identification' => '123456789',
        'password' => 'newpassword123',
        'password_confirmation' => 'mismatching_password',
    ];

    $response = $this->actingAs($this->appUser, 'sanctum')
        ->putJson('/api/app-user/profile', $data);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

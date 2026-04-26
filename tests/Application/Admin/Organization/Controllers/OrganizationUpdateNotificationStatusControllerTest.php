<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\Organization\Models\Organization;
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

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create();

    // Create some active notification channels
    $this->organization->notificationChannels()->createMany([
        ['channel_type' => 'email', 'channel_value' => 'test@test.com', 'is_active' => true, 'priority' => 1],
        ['channel_type' => 'whatsapp', 'channel_value' => '+573001234567', 'is_active' => true, 'priority' => 1],
        ['channel_type' => 'internal', 'channel_value' => 'internal', 'is_active' => true, 'priority' => 1],
    ]);
});

it('deactivates all notification channels for an organization', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson("/api/admin/organizations/{$this->organization->id}/notifications-status", [
            'is_active' => false,
        ]);

    $response->assertStatus(204);

    expect($this->organization->notificationChannels()->where('is_active', true)->count())->toBe(0);
});

it('activates all notification channels for an organization', function (): void {
    // First deactivate them
    $this->organization->notificationChannels()->update(['is_active' => false]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/admin/organizations/{$this->organization->id}/notifications-status", [
            'is_active' => true,
        ]);

    $response->assertStatus(204);

    expect($this->organization->notificationChannels()->where('is_active', true)->count())->toBe(3);
});

it('requires admin authentication to update notification status', function (): void {
    $response = $this->postJson("/api/admin/organizations/{$this->organization->id}/notifications-status", [
        'is_active' => false,
    ]);

    $response->assertStatus(401);
});

it('returns 404 if organization does not exist', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/organizations/non-existent-id/notifications-status', [
            'is_active' => false,
        ]);

    $response->assertStatus(404);
});

it('validates is_active is required', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson("/api/admin/organizations/{$this->organization->id}/notifications-status", []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['is_active']);
});

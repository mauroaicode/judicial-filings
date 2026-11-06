<?php

declare(strict_types=1);

namespace Tests\Application\Admin\Organization\Controllers;

use Illuminate\Support\Facades\Hash;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
use Src\Domain\Notification\Models\OrganizationNotificationChannelLimit;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    config(['organization.defaults.notification_channel_limit' => 3]);

    $this->user = User::factory()->create([
        'email' => 'admin-channel-limits@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create(['is_active' => true]);
});

it('requires authentication to view notification channel limits', function (): void {
    $this->getJson("/api/admin/organizations/{$this->organization->id}/notification-channel-limits")
        ->assertUnauthorized();
});

it('returns the default quota when the organization has no overrides', function (): void {
    OrganizationNotificationChannel::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'email',
        'channel_value' => 'aviso@example.com',
        'is_active' => true,
        'priority' => 1,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/admin/organizations/{$this->organization->id}/notification-channel-limits")
        ->assertOk()
        ->assertJsonPath('organization_id', $this->organization->id)
        ->assertJsonPath('default_limit', 3)
        ->assertJsonPath('channels.0.channel_type', 'email')
        ->assertJsonPath('channels.0.limit', 3)
        ->assertJsonPath('channels.0.configured_limit', null)
        ->assertJsonPath('channels.0.used', 1)
        ->assertJsonPath('channels.0.can_add', true)
        ->assertJsonPath('channels.1.channel_type', 'whatsapp')
        ->assertJsonPath('channels.1.limit', 3)
        ->assertJsonPath('channels.2.channel_type', 'sms')
        ->assertJsonPath('channels.2.limit', 3);
});

it('saves a different limit per channel and can clear it back to the default', function (): void {
    $this->actingAs($this->user)
        ->putJson("/api/admin/organizations/{$this->organization->id}/notification-channel-limits", [
            'email' => 5,
            'whatsapp' => 2,
            'sms' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('limits.channels.0.channel_type', 'email')
        ->assertJsonPath('limits.channels.0.limit', 5)
        ->assertJsonPath('limits.channels.0.configured_limit', 5)
        ->assertJsonPath('limits.channels.1.limit', 2)
        ->assertJsonPath('limits.channels.2.limit', 1);

    expect(OrganizationNotificationChannelLimit::query()->where('organization_id', $this->organization->id)->count())
        ->toBe(3);

    $this->actingAs($this->user)
        ->putJson("/api/admin/organizations/{$this->organization->id}/notification-channel-limits", [
            'email' => null,
            'sms' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('limits.channels.0.configured_limit', null)
        ->assertJsonPath('limits.channels.0.limit', 3)
        ->assertJsonPath('limits.channels.1.limit', 2)
        ->assertJsonPath('limits.channels.2.limit', 0)
        ->assertJsonPath('limits.channels.2.can_add', false);
});

it('rejects a limit outside the allowed range', function (): void {
    $this->actingAs($this->user)
        ->putJson("/api/admin/organizations/{$this->organization->id}/notification-channel-limits", [
            'email' => 101,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

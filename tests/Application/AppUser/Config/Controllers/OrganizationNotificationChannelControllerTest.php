<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\Config\Controllers;

use Illuminate\Support\Facades\Mail;
use Src\Application\Shared\Mail\OrganizationNotificationChannelEmailVerificationMailable;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
use Src\Domain\Notification\Models\OrganizationNotificationChannelLimit;
use Src\Domain\Notification\Models\ValidatedNotificationEmail;
use Src\Domain\Organization\Models\Organization;

beforeEach(function (): void {
    Mail::fake();
    config(['organization.defaults.notification_channel_limit' => 3]);

    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create();
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);
});

it('requires authentication to list notification channels', function (): void {
    $this->getJson('/api/app-user/config/notification-channels')
        ->assertUnauthorized();
});

it('rejects an app user without an organization', function (): void {
    $user = AppUser::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/app-user/config/notification-channels')
        ->assertStatus(422);
});

it('lists configurable channels and the default quota', function (): void {
    OrganizationNotificationChannel::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'email',
        'channel_value' => 'aviso@example.com',
        'is_active' => true,
        'priority' => 1,
    ]);
    OrganizationNotificationChannel::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'internal',
        'channel_value' => 'internal',
        'is_active' => true,
        'priority' => 1,
    ]);

    $response = $this->actingAs($this->appUser, 'sanctum')
        ->getJson('/api/app-user/config/notification-channels')
        ->assertOk()
        ->assertJsonPath('limits.default_limit', 3)
        ->assertJsonPath('limits.channels.0.channel_type', 'email')
        ->assertJsonPath('limits.channels.0.limit', 3)
        ->assertJsonPath('limits.channels.0.configured_limit', null)
        ->assertJsonPath('limits.channels.0.used', 1)
        ->assertJsonPath('limits.channels.0.remaining', 2)
        ->assertJsonPath('limits.channels.0.can_add', true)
        ->assertJsonPath('limits.channels.1.channel_type', 'whatsapp')
        ->assertJsonPath('limits.channels.2.channel_type', 'sms');

    $types = collect($response->json('channels'))->pluck('channel_type');
    expect($types->all())->toBe(['email'])
        ->and($response->json('channels.0.channel_value'))->toBe('aviso@example.com')
        ->and($response->json('channels.0.is_active'))->toBeTrue();
});

it('lists the quota on its own endpoint', function (): void {
    OrganizationNotificationChannelLimit::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'whatsapp',
        'max_channels' => 1,
    ]);

    $this->actingAs($this->appUser, 'sanctum')
        ->getJson('/api/app-user/config/notification-channels/limits')
        ->assertOk()
        ->assertJsonPath('default_limit', 3)
        ->assertJsonPath('channels.1.channel_type', 'whatsapp')
        ->assertJsonPath('channels.1.limit', 1)
        ->assertJsonPath('channels.1.configured_limit', 1)
        ->assertJsonPath('channels.0.limit', 3);
});

it('creates an email channel and assigns the next priority', function (): void {
    OrganizationNotificationChannel::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'email',
        'channel_value' => 'primero@example.com',
        'is_active' => true,
        'priority' => 1,
    ]);

    $this->actingAs($this->appUser, 'sanctum')
        ->postJson('/api/app-user/config/notification-channels', [
            'channel_type' => 'email',
            'channel_value' => ' Segundo@Example.com ',
        ])
        ->assertCreated()
        ->assertJsonPath('channel_type', 'email')
        ->assertJsonPath('channel_value', 'segundo@example.com')
        ->assertJsonPath('is_active', true)
        ->assertJsonPath('is_validated', false)
        ->assertJsonPath('priority', 2);

    Mail::assertSent(OrganizationNotificationChannelEmailVerificationMailable::class, function (OrganizationNotificationChannelEmailVerificationMailable $mail): bool {
        return $mail->hasTo('segundo@example.com');
    });

    $html = (new OrganizationNotificationChannelEmailVerificationMailable(
        email: 'segundo@example.com',
        confirmUrl: 'https://app.example.com/confirmar',
        expiresInHours: 72,
    ))->render();

    expect($html)->toContain('Confirma este correo')
        ->and($html)->toContain('segundo@example.com')
        ->and($html)->toContain('logo-notijudicial');
});

it('creates a whatsapp channel', function (): void {
    $this->actingAs($this->appUser, 'sanctum')
        ->postJson('/api/app-user/config/notification-channels', [
            'channel_type' => 'whatsapp',
            'channel_value' => '+57 300 123 4567',
            'is_active' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('channel_value', '+573001234567')
        ->assertJsonPath('is_active', false)
        ->assertJsonPath('is_validated', true);

    Mail::assertNothingSent();
});

it('rejects an invalid email and a duplicate destination', function (): void {
    OrganizationNotificationChannel::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'sms',
        'channel_value' => '+573001112233',
        'is_active' => true,
        'priority' => 1,
    ]);

    $this->actingAs($this->appUser, 'sanctum')
        ->postJson('/api/app-user/config/notification-channels', [
            'channel_type' => 'email',
            'channel_value' => 'no-es-correo',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['channel_value']);

    $this->actingAs($this->appUser, 'sanctum')
        ->postJson('/api/app-user/config/notification-channels', [
            'channel_type' => 'sms',
            'channel_value' => '+573001112233',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['channel_value']);
});

it('rejects a new channel once the organization reaches its limit', function (): void {
    config(['organization.defaults.notification_channel_limit' => 3]);

    OrganizationNotificationChannelLimit::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'email',
        'max_channels' => 2,
    ]);

    foreach (['uno@example.com', 'dos@example.com'] as $index => $email) {
        OrganizationNotificationChannel::query()->create([
            'organization_id' => $this->organization->id,
            'channel_type' => 'email',
            'channel_value' => $email,
            'is_active' => true,
            'priority' => $index + 1,
        ]);
    }

    $this->actingAs($this->appUser, 'sanctum')
        ->postJson('/api/app-user/config/notification-channels', [
            'channel_type' => 'email',
            'channel_value' => 'tres@example.com',
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.channel_type.0', __('organization.notification_channel_limit_reached', [
            'limit' => 2,
            'channel' => trans_choice('organization.notification_channel_types.email', 2),
        ]));
});

it('uses the env default when the organization has no override', function (): void {
    config(['organization.defaults.notification_channel_limit' => 1]);

    OrganizationNotificationChannel::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'sms',
        'channel_value' => '+573009998877',
        'is_active' => true,
        'priority' => 1,
    ]);

    $this->actingAs($this->appUser, 'sanctum')
        ->postJson('/api/app-user/config/notification-channels', [
            'channel_type' => 'sms',
            'channel_value' => '+573001110000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['channel_type']);
});

it('updates and deletes a channel that belongs to the organization', function (): void {
    $channel = OrganizationNotificationChannel::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'email',
        'channel_value' => 'viejo@example.com',
        'is_active' => true,
        'priority' => 1,
    ]);

    $this->actingAs($this->appUser, 'sanctum')
        ->putJson("/api/app-user/config/notification-channels/{$channel->id}", [
            'channel_value' => 'nuevo@example.com',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('channel_value', 'nuevo@example.com')
        ->assertJsonPath('is_active', false)
        ->assertJsonPath('is_validated', false);

    Mail::assertSent(OrganizationNotificationChannelEmailVerificationMailable::class, function (OrganizationNotificationChannelEmailVerificationMailable $mail): bool {
        return $mail->hasTo('nuevo@example.com');
    });

    $this->actingAs($this->appUser, 'sanctum')
        ->deleteJson("/api/app-user/config/notification-channels/{$channel->id}")
        ->assertNoContent();

    expect(OrganizationNotificationChannel::query()->whereKey($channel->id)->exists())->toBeFalse();
});

it('does not allow managing a channel from another organization', function (): void {
    $other = Organization::factory()->create();
    $channel = OrganizationNotificationChannel::query()->create([
        'organization_id' => $other->id,
        'channel_type' => 'email',
        'channel_value' => 'otro@example.com',
        'is_active' => true,
        'priority' => 1,
    ]);

    $this->actingAs($this->appUser, 'sanctum')
        ->putJson("/api/app-user/config/notification-channels/{$channel->id}", [
            'channel_value' => 'hack@example.com',
        ])
        ->assertNotFound();
});

it('activates a new email immediately when that address was already confirmed', function (): void {
    ValidatedNotificationEmail::remember('conocido@example.com');

    $this->actingAs($this->appUser, 'sanctum')
        ->postJson('/api/app-user/config/notification-channels', [
            'channel_type' => 'email',
            'channel_value' => 'Conocido@Example.com',
        ])
        ->assertCreated()
        ->assertJsonPath('channel_value', 'conocido@example.com')
        ->assertJsonPath('is_validated', true);

    Mail::assertNothingSent();
});

it('keeps a confirmed email validated when only the active flag changes', function (): void {
    $channel = OrganizationNotificationChannel::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'email',
        'channel_value' => 'fijo@example.com',
        'is_active' => true,
        'priority' => 1,
    ]);

    $this->actingAs($this->appUser, 'sanctum')
        ->putJson("/api/app-user/config/notification-channels/{$channel->id}", [
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('is_active', false)
        ->assertJsonPath('is_validated', true);

    Mail::assertNothingSent();
});

it('resends the confirmation email only while the address is pending', function (): void {
    $channel = OrganizationNotificationChannel::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'email',
        'channel_value' => 'pendiente@example.com',
        'is_active' => true,
        'validated_at' => null,
        'priority' => 1,
    ]);

    $this->actingAs($this->appUser, 'sanctum')
        ->postJson("/api/app-user/config/notification-channels/{$channel->id}/resend-verification")
        ->assertOk()
        ->assertJsonPath('is_validated', false);

    Mail::assertSent(OrganizationNotificationChannelEmailVerificationMailable::class, function (OrganizationNotificationChannelEmailVerificationMailable $mail): bool {
        return $mail->hasTo('pendiente@example.com');
    });

    $channel->update(['validated_at' => now()]);

    $this->actingAs($this->appUser, 'sanctum')
        ->postJson("/api/app-user/config/notification-channels/{$channel->id}/resend-verification")
        ->assertStatus(422);
});

it('does not deliver a notification email until it is confirmed', function (): void {
    $pending = OrganizationNotificationChannel::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'email',
        'channel_value' => 'pendiente@example.com',
        'is_active' => true,
        'validated_at' => null,
        'priority' => 1,
    ]);
    $ready = OrganizationNotificationChannel::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'email',
        'channel_value' => 'listo@example.com',
        'is_active' => true,
        'priority' => 2,
    ]);

    $ids = OrganizationNotificationChannel::query()
        ->where('organization_id', $this->organization->id)
        ->deliverable()
        ->pluck('id');

    expect($ids->contains($ready->id))->toBeTrue()
        ->and($ids->contains($pending->id))->toBeFalse();
});

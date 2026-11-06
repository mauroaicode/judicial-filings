<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\Config\Controllers;

use Illuminate\Support\Facades\URL;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
use Src\Domain\Notification\Models\ValidatedNotificationEmail;
use Src\Domain\Organization\Models\Organization;

beforeEach(function (): void {
    config(['app.frontend_url' => 'https://app.example.com']);

    $this->organization = Organization::factory()->create();
});

it('confirms the email and every other pending channel that uses it', function (): void {
    $channel = OrganizationNotificationChannel::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'email',
        'channel_value' => 'nuevo@example.com',
        'is_active' => true,
        'validated_at' => null,
        'priority' => 1,
    ]);
    $otherOrganization = Organization::factory()->create();
    $other = OrganizationNotificationChannel::query()->create([
        'organization_id' => $otherOrganization->id,
        'channel_type' => 'email',
        'channel_value' => 'nuevo@example.com',
        'is_active' => true,
        'validated_at' => null,
        'priority' => 1,
    ]);

    $url = URL::temporarySignedRoute('notification-channels.email.confirm', now()->addHour(), [
        'channel' => $channel->id,
        'email' => $channel->channel_value,
    ]);

    $this->get($url)->assertRedirect('https://app.example.com/confirmar-correo?estado=ok');

    expect($channel->refresh()->validated_at)->not->toBeNull()
        ->and($other->refresh()->validated_at)->not->toBeNull()
        ->and(ValidatedNotificationEmail::contains('nuevo@example.com'))->toBeTrue();
});

it('rejects an expired or tampered confirmation link', function (): void {
    $channel = OrganizationNotificationChannel::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'email',
        'channel_value' => 'nuevo@example.com',
        'is_active' => true,
        'validated_at' => null,
        'priority' => 1,
    ]);

    $expired = URL::temporarySignedRoute('notification-channels.email.confirm', now()->subMinute(), [
        'channel' => $channel->id,
        'email' => $channel->channel_value,
    ]);

    $this->get($expired)->assertRedirect('https://app.example.com/confirmar-correo?estado=expired');

    $this->get('/notification-channels/email/confirm/'.$channel->id.'?email=nuevo@example.com&signature=invalida')
        ->assertRedirect('https://app.example.com/confirmar-correo?estado=invalid');

    expect($channel->refresh()->validated_at)->toBeNull();
});

it('does not confirm the channel when the link email no longer matches', function (): void {
    $channel = OrganizationNotificationChannel::query()->create([
        'organization_id' => $this->organization->id,
        'channel_type' => 'email',
        'channel_value' => 'actual@example.com',
        'is_active' => true,
        'validated_at' => null,
        'priority' => 1,
    ]);

    $url = URL::temporarySignedRoute('notification-channels.email.confirm', now()->addHour(), [
        'channel' => $channel->id,
        'email' => 'anterior@example.com',
    ]);

    $this->get($url)->assertRedirect('https://app.example.com/confirmar-correo?estado=invalid');

    expect($channel->refresh()->validated_at)->toBeNull();
});

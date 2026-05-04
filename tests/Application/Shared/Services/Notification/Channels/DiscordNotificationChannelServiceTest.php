<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Spatie\DiscordAlerts\Jobs\SendToDiscordChannelJob;
use Src\Application\Shared\Services\Notification\Channels\DiscordNotificationChannelService;

beforeEach(function (): void {
    Queue::fake();
});

it('does not dispatch when webhook URL is missing', function (): void {
    config([
        'discord-alerts.webhook_urls.log_sync_daily' => '',
    ]);

    app(DiscordNotificationChannelService::class)
        ->send(DiscordNotificationChannelService::CHANNEL_LOG_SYNC_DAILY, 'hello');

    Queue::assertNothingPushed();
});

it('dispatches discord job when webhook is configured', function (): void {
    config([
        'discord-alerts.webhook_urls.log_sync_daily' => 'https://discord.com/api/webhooks/123456789/abcdefghijklmnopqrstuvwxyz',
    ]);

    app(DiscordNotificationChannelService::class)
        ->send(DiscordNotificationChannelService::CHANNEL_LOG_SYNC_DAILY, 'test message');

    Queue::assertPushed(SendToDiscordChannelJob::class);
});

it('reports canSend false when webhook missing', function (): void {
    config(['discord-alerts.webhook_urls.log_sync_daily' => '']);

    expect(app(DiscordNotificationChannelService::class)->canSend(DiscordNotificationChannelService::CHANNEL_LOG_SYNC_DAILY))
        ->toBeFalse();
});

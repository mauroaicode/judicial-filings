<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Spatie\DiscordAlerts\Facades\DiscordAlert;

/**
 * Sends Discord alerts using configured webhook keys from {@see config('discord-alerts.webhook_urls')}.
 * Add new keys there + matching env vars for additional notification surfaces (e.g. imports, billing).
 */
readonly class DiscordNotificationChannelService
{
    /** Webhook key for daily judicial sync reports (#log-sync-daily). */
    public const CHANNEL_LOG_SYNC_DAILY = 'log_sync_daily';

    /**
     * @param  array<int, array<string, mixed>>  $embeds  Discord embed payloads (see Spatie docs).
     */
    public function send(string $channelKey, string $message, array $embeds = []): void
    {
        if (! $this->canSend($channelKey)) {
            return;
        }

        if ($embeds !== []) {
            DiscordAlert::to($channelKey)->message($message, $embeds);
        } else {
            DiscordAlert::to($channelKey)->message($message);
        }
    }

    public function canSend(string $channelKey): bool
    {
        $url = config("discord-alerts.webhook_urls.{$channelKey}");

        return is_string($url) && $url !== '' && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}

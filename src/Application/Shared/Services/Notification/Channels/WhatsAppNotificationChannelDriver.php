<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Contracts\Notification\NotificationChannelDriverInterface;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

class WhatsAppNotificationChannelDriver implements NotificationChannelDriverInterface
{
    public function send(OrganizationNotification $notification, OrganizationNotificationChannel $channel): void
    {
        $to = $channel->channel_value;
        if (empty($to)) {
            throw new \InvalidArgumentException('WhatsApp channel has no recipient.');
        }

        $message = $this->buildMessage($notification);

        try {
            // Placeholder: integrate with your WhatsApp API (Twilio, WhatsApp Business API, etc.)
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->info('WhatsApp notification (placeholder)', [
                    'to' => $to,
                    'message' => $message,
                    'notification_type' => $notification->notification_type,
                ]);

            // When integrating a real gateway, call it here and throw on failure.
        } catch (\Throwable $e) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->error('WhatsApp notification send failed', [
                    'channel_id' => $channel->id,
                    'message' => $e->getMessage(),
                ]);

            throw $e;
        }
    }

    private function buildMessage(OrganizationNotification $notification): string
    {
        $type = $notification->notification_type;

        return "Notificación judicial: {$type}";
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Contracts\Notification\NotificationChannelDriverInterface;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

class SmsNotificationChannelDriver implements NotificationChannelDriverInterface
{
    public function send(OrganizationNotification $notification, OrganizationNotificationChannel $channel): void
    {
        $to = $channel->channel_value;
        if (empty($to)) {
            throw new \InvalidArgumentException('SMS channel has no recipient number.');
        }

        $message = $this->buildMessage($notification);

        try {
            // Placeholder: integrate with your SMS gateway (Twilio, etc.)
            // For now we log; replace with actual HTTP/gateway call.
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->info('SMS notification (placeholder)', [
                    'to' => $to,
                    'message' => $message,
                    'notification_type' => $notification->notification_type,
                ]);

            // When integrating a real gateway, call it here and throw on failure.
            // Example: $this->smsGateway->send($to, $message);
        } catch (\Throwable $e) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->error('SMS notification send failed', [
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

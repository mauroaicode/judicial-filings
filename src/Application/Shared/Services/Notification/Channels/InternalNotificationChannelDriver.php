<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Contracts\Notification\NotificationChannelDriverInterface;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

class InternalNotificationChannelDriver implements NotificationChannelDriverInterface
{
    public function send(OrganizationNotification $notification, OrganizationNotificationChannel $channel): void
    {
        // Internal channel: notification is already stored in organization_notifications.
        // This driver can log for audit or trigger in-app display; no external delivery.
        try {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->info('Internal notification recorded', [
                    'organization_id' => $notification->organization_id,
                    'notification_type' => $notification->notification_type,
                    'notifiable_type' => $notification->notifiable_type,
                    'notifiable_id' => $notification->notifiable_id,
                ]);
        } catch (\Throwable $e) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->error('Internal notification send failed', [
                    'channel_id' => $channel->id,
                    'message' => $e->getMessage(),
                ]);

            throw $e;
        }
    }
}

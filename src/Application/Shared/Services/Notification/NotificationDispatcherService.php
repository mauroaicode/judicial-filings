<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Contracts\Notification\NotificationChannelDriverInterface;
use Src\Application\Shared\Services\Notification\Channels\EmailNotificationChannelDriver;
use Src\Application\Shared\Services\Notification\Channels\InternalNotificationChannelDriver;
use Src\Application\Shared\Services\Notification\Channels\SmsNotificationChannelDriver;
use Src\Application\Shared\Services\Notification\Channels\WhatsAppNotificationChannelDriver;
use Src\Domain\Notification\Models\HistoryOrganizationChannelNotification;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

class NotificationDispatcherService
{
    /**
     * Map channel_type (from DB) to driver implementation.
     *
     * @var array<string, class-string<NotificationChannelDriverInterface>>
     */
    private const CHANNEL_DRIVERS = [
        'email' => EmailNotificationChannelDriver::class,
        'sms' => SmsNotificationChannelDriver::class,
        'whatsapp' => WhatsAppNotificationChannelDriver::class,
        'internal' => InternalNotificationChannelDriver::class,
    ];

    public function handle(OrganizationNotification $notification): void
    {
        $organization = $notification->organization;
        $channels = $organization->notificationChannels()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        if ($channels->isEmpty()) {
            return;
        }

        $atLeastOneSuccess = false;

        foreach ($channels as $channel) {
            $driver = $this->getDriver($channel->channel_type);
            if (! $driver instanceof \Src\Application\Shared\Contracts\Notification\NotificationChannelDriverInterface) {
                Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                    ->warning('Unknown notification channel type', ['channel_type' => $channel->channel_type]);

                continue;
            }

            try {
                $driver->send($notification, $channel);
                $this->recordHistorySuccess($notification, $channel);
                $atLeastOneSuccess = true;
            } catch (\Throwable $e) {
                $this->recordHistoryFailure($notification, $channel);
                Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                    ->error('Notification send failed for channel', [
                        'channel_id' => $channel->id,
                        'channel_type' => $channel->channel_type,
                        'message' => $e->getMessage(),
                    ]);
            }
        }

        if ($atLeastOneSuccess) {
            $this->markNotificationAsSent($notification);
        }
    }

    private function getDriver(string $channelType): ?NotificationChannelDriverInterface
    {
        $class = self::CHANNEL_DRIVERS[$channelType] ?? null;

        return $class !== null ? resolve($class) : null;
    }

    private function recordHistorySuccess(OrganizationNotification $notification, OrganizationNotificationChannel $channel): void
    {
        HistoryOrganizationChannelNotification::query()->create([
            'organization_notification_channel_id' => $channel->id,
            'notifiable_id' => $notification->notifiable_id,
            'notifiable_type' => $notification->notifiable_type,
            'notification_type' => $notification->notification_type,
            'is_notified' => true,
            'notified_at' => now(),
        ]);
    }

    private function recordHistoryFailure(OrganizationNotification $notification, OrganizationNotificationChannel $channel): void
    {
        HistoryOrganizationChannelNotification::query()->create([
            'organization_notification_channel_id' => $channel->id,
            'notifiable_id' => $notification->notifiable_id,
            'notifiable_type' => $notification->notifiable_type,
            'notification_type' => $notification->notification_type,
            'is_notified' => false,
            'notified_at' => null,
        ]);
    }

    private function markNotificationAsSent(OrganizationNotification $notification): void
    {
        $notification->update([
            'is_notified' => true,
            'notified_at' => now(),
        ]);
    }
}

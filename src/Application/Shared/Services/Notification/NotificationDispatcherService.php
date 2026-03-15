<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Src\Application\Shared\Contracts\Notification\NotificationChannelDriverInterface;
use Src\Application\Shared\Jobs\DeliverNotificationChannelJob;
use Src\Application\Shared\Services\Notification\Channels\EmailNotificationChannelDriver;
use Src\Application\Shared\Services\Notification\Channels\InternalNotificationChannelDriver;
use Src\Application\Shared\Services\Notification\Channels\SmsNotificationChannelDriver;
use Src\Application\Shared\Services\Notification\Channels\WhatsAppNotificationChannelDriver;
use Src\Domain\Notification\Models\OrganizationNotification;

class NotificationDispatcherService
{
    /**
     * Map channel_type (from DB) to driver implementation.
     *
     * @var array<string, class-string<NotificationChannelDriverInterface>>
     */
    public const CHANNEL_DRIVERS = [
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

        $queues = config('judicial-sync.queues', []);

        foreach ($channels as $channel) {
            $queueName = $queues[$channel->channel_type] ?? 'notifications';

            DeliverNotificationChannelJob::dispatch(
                $notification->id,
                $channel->id,
                $channel->channel_type
            )->onQueue($queueName);
        }
    }

    public function getDriver(string $channelType): ?NotificationChannelDriverInterface
    {
        $class = self::CHANNEL_DRIVERS[$channelType] ?? null;

        return $class !== null ? resolve($class) : null;
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Contracts\Notification\NotificationChannelDriverInterface;
use Src\Application\Shared\Jobs\DeliverNotificationChannelJob;
use Src\Application\Shared\Services\Notification\Channels\EmailNotificationChannelDriver;
use Src\Application\Shared\Services\Notification\Channels\InternalNotificationChannelDriver;
use Src\Application\Shared\Services\Notification\Channels\SmsNotificationChannelDriver;
use Src\Application\Shared\Services\Notification\Channels\WhatsAppNotificationChannelDriver;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Process\Models\ProcessAction;

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
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->warning('NotificationDispatcherService: organization has no active channels', [
                    'organization_id' => $organization->id,
                    'notification_id' => $notification->id,
                ]);

            return;
        }

        $queues = config('judicial-sync.queues', []);

        foreach ($channels as $channel) {
            // Skip email and internal for immediate dispatch to allow for consolidated digest
            // BUT ONLY IF it's a judicial action. Other notifications (like import finished) should be immediate.
            $isJudicialAction = $notification->notifiable_type === (new ProcessAction())->getMorphClass();

            if ($isJudicialAction && in_array($channel->channel_type, ['email', 'internal'], true)) {
                continue;
            }

            $queueName = $queues[$channel->channel_type] ?? 'notifications';

            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->info('NotificationDispatcherService: Dispatching channel job', [
                    'notification_id' => $notification->id,
                    'channel_type' => $channel->channel_type,
                    'queue' => $queueName,
                ]);

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

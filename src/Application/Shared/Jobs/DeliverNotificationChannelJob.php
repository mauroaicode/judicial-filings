<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Src\Application\Shared\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Contracts\Notification\NotificationChannelDriverInterface;
use Src\Application\Shared\Services\Notification\NotificationDispatcherService;
use Src\Domain\Notification\Models\HistoryOrganizationChannelNotification;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

class DeliverNotificationChannelJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $tries = 3;

    /** @var int */
    public $backoff = 30;

    /** @var int */
    public $timeout = 60;

    public function __construct(
        public string $notificationId,
        public string $channelId,
        public string $channelType
    ) {
        $notificationConfig = config('judicial-sync.jobs.send_notification_dispatcher', []);
        $this->backoff = $notificationConfig['backoff'] ?? 30;
        $this->timeout = $notificationConfig['timeout'] ?? 60;
        if (! empty($notificationConfig['connection'])) {
            $this->connection = $notificationConfig['connection'];
        }
    }

    /**
     * Dynamic tries based on a channel type.
     */
    public function tries(): int
    {
        return match ($this->channelType) {
            'sms', 'whatsapp' => 2,
            'internal'        => 3,
            default           => 3,
        };
    }

    /**
     * @throws \Throwable
     */
    public function handle(NotificationDispatcherService $dispatcher): void
    {
        $channel = OrganizationNotificationChannel::query()->find($this->channelId);
        $notification = OrganizationNotification::query()->where('id', $this->notificationId)->first();

        $logChannel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        if ($channel === null || $notification === null) {
            Log::channel($logChannel)->warning('DeliverNotificationChannelJob: Record not found', [
                'notification_id' => $this->notificationId,
                'channel_id' => $this->channelId,
                'notification_found' => $notification !== null,
                'channel_found' => $channel !== null,
            ]);
            return;
        }

        Log::channel($logChannel)->info('DeliverNotificationChannelJob: Processing job', [
            'notification_id' => $this->notificationId,
            'channel_id' => $this->channelId,
            'channel_type' => $this->channelType
        ]);

        $driver = $dispatcher->getDriver($channel->channel_type);

        if (! $driver instanceof NotificationChannelDriverInterface) {
            Log::channel($logChannel)->warning('DeliverNotificationChannelJob: Unknown driver', [
                'channel_type' => $channel->channel_type
            ]);

            return;
        }

        try {
            $driver->send($notification, $channel);
            $this->recordHistory($notification, $channel, true);
        } catch (\Throwable $e) {
            $this->recordHistory($notification, $channel, false);

            Log::channel($logChannel)->error('DeliverNotificationChannelJob: Delivery failed', [
                'channel_id' => $channel->id,
                'channel_type' => $channel->channel_type,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function recordHistory(OrganizationNotification $notification, OrganizationNotificationChannel $channel, bool $success): void
    {
        HistoryOrganizationChannelNotification::query()->create([
            'organization_notification_channel_id' => $channel->id,
            'notifiable_id' => $notification->notifiable_id,
            'notifiable_type' => $notification->notifiable_type,
            'notification_type' => $notification->notification_type,
            'is_notified' => $success,
            'notified_at' => $success ? now() : null,
        ]);

        if ($success) {
            $notification->update([
                'is_notified' => true,
                'notified_at' => now(),
            ]);
        }
    }
}

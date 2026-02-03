<?php

declare(strict_types=1);

namespace Src\Application\Shared\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Services\Notification\NotificationDispatcherService;
use Src\Domain\Notification\Models\OrganizationNotification;

class SendOrganizationNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $tries = 3;

    /** @var int|array<int> */
    public $backoff = 30;

    /** @var int */
    public $timeout = 60;

    public function __construct(
        public string $organizationId,
        public string $notifiableId,
        public string $notifiableType,
        public string $notificationType
    ) {
        $config = config('judicial-sync.jobs.send_notification', []);
        $this->queue = $config['queue'] ?? 'notifications';
        $this->tries = $config['tries'] ?? 3;
        $this->backoff = $config['backoff'] ?? 30;
        $this->timeout = $config['timeout'] ?? 60;
        if (! empty($config['connection'])) {
            $this->connection = $config['connection'];
        }
    }

    public static function fromNotification(OrganizationNotification $notification): self
    {
        return new self(
            $notification->organization_id,
            $notification->notifiable_id,
            $notification->notifiable_type,
            $notification->notification_type
        );
    }

    public function handle(NotificationDispatcherService $dispatcher): void
    {
        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        $notification = OrganizationNotification::query()
            ->where('organization_id', $this->organizationId)
            ->where('notifiable_id', $this->notifiableId)
            ->where('notifiable_type', $this->notifiableType)
            ->where('notification_type', $this->notificationType)
            ->first();

        if ($notification === null) {
            Log::channel($channel)->warning('SendOrganizationNotificationJob: notification not found', [
                'organization_id' => $this->organizationId,
                'notifiable_id' => $this->notifiableId,
                'notifiable_type' => $this->notifiableType,
                'notification_type' => $this->notificationType,
            ]);

            return;
        }

        try {
            $dispatcher->handle($notification);
        } catch (\Throwable $e) {
            Log::channel($channel)->error('SendOrganizationNotificationJob failed', [
                'organization_id' => $this->organizationId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

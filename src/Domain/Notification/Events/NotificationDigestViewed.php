<?php

declare(strict_types=1);

namespace Src\Domain\Notification\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationDigestViewed implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public string $broadcastQueue = 'notifications';

    public function __construct(
        public string $notificationDigestId,
        public string $organizationId,
        public string $organizationName,
        public string $appUserId,
        public string $appUserName,
        public string $appUserLastName,
        public string $viewedAt,
        public string $openedVia,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.presence'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'NotificationDigestViewed';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'notification_digest_id' => $this->notificationDigestId,
            'organization_id' => $this->organizationId,
            'organization_name' => $this->organizationName,
            'app_user_id' => $this->appUserId,
            'app_user_name' => $this->appUserName,
            'app_user_last_name' => $this->appUserLastName,
            'viewed_at' => $this->viewedAt,
            'opened_via' => $this->openedVia,
        ];
    }
}

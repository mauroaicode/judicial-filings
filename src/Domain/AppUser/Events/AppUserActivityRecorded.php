<?php

declare(strict_types=1);

namespace Src\Domain\AppUser\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppUserActivityRecorded implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $appUserId,
        public bool $incrementLogin,
        public string $activityDate,
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
        return 'AppUserActivityRecorded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'app_user_id' => $this->appUserId,
            'increment_login' => $this->incrementLogin,
            'activity_date' => $this->activityDate,
        ];
    }
}

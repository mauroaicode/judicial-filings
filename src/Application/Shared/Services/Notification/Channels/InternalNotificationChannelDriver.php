<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Src\Application\Shared\Contracts\Notification\NotificationChannelDriverInterface;
use Src\Application\Shared\Notifications\JudicialActionDetectedNotification;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
use Src\Domain\Process\Models\ProcessAction;

class InternalNotificationChannelDriver implements NotificationChannelDriverInterface
{
    public function send(OrganizationNotification $notification, OrganizationNotificationChannel $channel): void
    {
        /** @var \Illuminate\Database\Eloquent\Model $action */
        $action = $notification->notifiable;
        if (! $action instanceof ProcessAction) {
            return;
        }

        $process = $action->process;
        $type = $notification->notification_type;
        $users = $notification->organization->appUsers;

        if ($users->isEmpty()) {
            return;
        }

        try {
            Notification::send(
                $users,
                new JudicialActionDetectedNotification($action, $process, $type)
            );

            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->info('Internal notification dispatched to organization users', [
                    'organization_id' => $notification->organization_id,
                    'user_count' => $users->count(),
                    'type' => $type,
                ]);
        } catch (\Throwable $e) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->error('Internal notification dispatch failed', [
                    'channel_id' => $channel->id,
                    'message' => $e->getMessage(),
                ]);

            throw $e;
        }
    }
}

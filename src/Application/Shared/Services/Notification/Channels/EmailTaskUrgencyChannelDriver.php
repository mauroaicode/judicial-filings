<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Src\Application\Shared\Contracts\Notification\TaskUrgencyChannelDriverInterface;
use Src\Application\Shared\DTOs\TaskUrgencyAlert;
use Src\Application\Shared\Notifications\TaskUrgencyMailNotification;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
use Throwable;

class EmailTaskUrgencyChannelDriver implements TaskUrgencyChannelDriverInterface
{
    public function send(TaskUrgencyAlert $alert, OrganizationNotificationChannel $channel): void
    {
        $recipient = $channel->channel_value;

        if (empty($recipient)) {
            throw new InvalidArgumentException('Email channel has no recipient address.');
        }

        try {
            Notification::route('mail', $recipient)
                ->notify(new TaskUrgencyMailNotification($alert));

            Log::channel(config('tasks.log_channel', 'stack'))->info('Task urgency email queued', [
                'task_id' => $alert->task->id,
                'channel_id' => $channel->id,
                'recipient' => $recipient,
                'urgency_level' => $alert->urgencyLevel->value,
            ]);
        } catch (Throwable $e) {
            Log::channel(config('tasks.log_channel', 'stack'))->error('Task urgency email failed', [
                'task_id' => $alert->task->id,
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

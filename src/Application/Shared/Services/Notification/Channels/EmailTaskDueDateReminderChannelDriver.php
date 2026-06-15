<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Src\Application\Shared\Contracts\Notification\TaskDueDateReminderChannelDriverInterface;
use Src\Application\Shared\DTOs\TaskDueDateReminderAlert;
use Src\Application\Shared\Notifications\TaskDueDateReminderMailNotification;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
use Throwable;

class EmailTaskDueDateReminderChannelDriver implements TaskDueDateReminderChannelDriverInterface
{
    public function send(TaskDueDateReminderAlert $alert, OrganizationNotificationChannel $channel): void
    {
        $recipient = $channel->channel_value;

        if (empty($recipient)) {
            throw new InvalidArgumentException('Email channel has no recipient address.');
        }

        try {
            Notification::route('mail', $recipient)
                ->notify(new TaskDueDateReminderMailNotification($alert));

            Log::channel(config('tasks.log_channel', 'stack'))->info('Task due-date reminder email queued', [
                'task_id' => $alert->task->id,
                'days_remaining' => $alert->daysRemaining,
                'recipient' => $recipient,
            ]);
        } catch (Throwable $e) {
            Log::channel(config('tasks.log_channel', 'stack'))->error('Task due-date reminder email failed', [
                'task_id' => $alert->task->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Contracts\Notification\TaskDueDateReminderChannelDriverInterface;
use Src\Application\Shared\DTOs\TaskDueDateReminderAlert;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

class WhatsAppTaskDueDateReminderChannelDriver implements TaskDueDateReminderChannelDriverInterface
{
    public function send(TaskDueDateReminderAlert $alert, OrganizationNotificationChannel $channel): void
    {
        $recipient = $channel->channel_value;

        if (empty($recipient)) {
            throw new \InvalidArgumentException('WhatsApp channel has no recipient number.');
        }

        // TODO: integrate with real WhatsApp provider.
        Log::channel(config('tasks.log_channel', 'stack'))->info('Task due-date reminder WhatsApp (placeholder)', [
            'task_id' => $alert->task->id,
            'recipient' => $recipient,
            'message' => $this->buildMessage($alert),
        ]);
    }

    private function buildMessage(TaskDueDateReminderAlert $alert): string
    {
        return __('task.due_reminder_whatsapp_message', [
            'title' => $alert->task->title,
            'days' => $alert->daysRemaining,
            'url' => $alert->taskUrl,
        ]);
    }
}

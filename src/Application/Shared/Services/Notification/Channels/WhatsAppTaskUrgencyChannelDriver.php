<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Contracts\Notification\TaskUrgencyChannelDriverInterface;
use Src\Application\Shared\DTOs\TaskUrgencyAlert;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

class WhatsAppTaskUrgencyChannelDriver implements TaskUrgencyChannelDriverInterface
{
    public function send(TaskUrgencyAlert $alert, OrganizationNotificationChannel $channel): void
    {
        $recipient = $channel->channel_value;

        if (empty($recipient)) {
            throw new \InvalidArgumentException('WhatsApp channel has no recipient number.');
        }

        // TODO: integrate with real WhatsApp provider.
        Log::channel(config('tasks.log_channel', 'stack'))->info('Task urgency WhatsApp notification (placeholder)', [
            'task_id' => $alert->task->id,
            'channel_id' => $channel->id,
            'recipient' => $recipient,
            'message' => $this->buildMessage($alert),
        ]);
    }

    private function buildMessage(TaskUrgencyAlert $alert): string
    {
        return __('task.urgency_whatsapp_message', [
            'title' => $alert->task->title,
            'days' => $alert->daysElapsed,
            'url' => $alert->taskUrl,
        ]);
    }
}

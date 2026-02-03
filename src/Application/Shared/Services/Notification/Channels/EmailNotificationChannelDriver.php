<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Src\Application\Shared\Contracts\Notification\NotificationChannelDriverInterface;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

class EmailNotificationChannelDriver implements NotificationChannelDriverInterface
{
    /**
     * @throws \Throwable
     */
    public function send(OrganizationNotification $notification, OrganizationNotificationChannel $channel): void
    {
        $to = $channel->channel_value;
        if (empty($to)) {
            throw new \InvalidArgumentException('Email channel has no recipient address.');
        }

        $subject = $this->buildSubject($notification);
        $body = $this->buildBody($notification);

        try {
            //            Mail::raw($body, function ($message) use ($to, $subject): void {
            //                $message->to($to)->subject($subject);
            //            });

            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->info('Envio de correo por email', [
                    'channel_id' => $channel->id,
                    'subject' => $subject,
                    'message' => $body,
                ]);

        } catch (\Throwable $e) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->error('Email notification send failed', [
                    'channel_id' => $channel->id,
                    'message' => $e->getMessage(),
                ]);

            throw $e;
        }
    }

    private function buildSubject(OrganizationNotification $notification): string
    {
        return 'Notificación judicial: '.$notification->notification_type;
    }

    private function buildBody(OrganizationNotification $notification): string
    {
        $notifiable = $notification->notifiable;
        $type = $notification->notification_type;

        return "Tipo: {$type}\n\nNotifiable: ".($notifiable ? $notifiable->getMorphClass().' #'.$notifiable->getKey() : 'N/A');
    }
}

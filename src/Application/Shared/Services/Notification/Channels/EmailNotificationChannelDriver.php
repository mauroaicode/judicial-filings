<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Src\Application\Shared\Contracts\Notification\NotificationChannelDriverInterface;
use Src\Application\Shared\Mail\JudicialActionDetectedMailable;
use Src\Application\Shared\Mail\ProcessBecamePrivateMailable;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Throwable;

class EmailNotificationChannelDriver implements NotificationChannelDriverInterface
{
    /**
     * @throws Throwable
     */
    public function send(OrganizationNotification $notification, OrganizationNotificationChannel $channel): void
    {
        $to = $channel->channel_value;

        if (empty($to)) {
            throw new \InvalidArgumentException('Email channel has no recipient address.');
        }

        $notifiable = $notification->notifiable;
        $type = $notification->notification_type;

        // Proceso pasó a privado — correo dedicado, sin necesidad de ProcessAction.
        if ($notifiable instanceof Process && $type === 'process_became_private') {
            $this->sendBecamePrivateMail($to, $notifiable, $notification);

            return;
        }

        /** @var \Illuminate\Database\Eloquent\Model $action */
        $action = $notifiable;
        if (! $action instanceof ProcessAction) {
            return;
        }

        $process = $action->process;

        // Add defensive delay to prevent Mailgun rate limiting for new accounts
        \Illuminate\Support\Sleep::sleep(2);

        try {
            Mail::to($to)->send(new JudicialActionDetectedMailable(
                $action,
                $process,
                $notification->organization_id,
                $type
            ));

            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->info('Judicial action email sent', [
                    'organization_id' => $notification->organization_id,
                    'process_number' => $process->process_number,
                    'type' => $type,
                ]);

        } catch (Throwable $e) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->error('Email notification send failed', [
                    'channel_id' => $channel->id,
                    'message' => $e->getMessage(),
                ]);

            throw $e;
        }
    }

    private function sendBecamePrivateMail(
        string $to,
        Process $process,
        OrganizationNotification $notification,
    ): void {
        try {
            Mail::to($to)->send(new ProcessBecamePrivateMailable($process));

            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->info('Process became-private email sent', [
                    'organization_id' => $notification->organization_id,
                    'process_number' => $process->process_number,
                ]);
        } catch (Throwable $e) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->error('Process became-private email failed', [
                    'organization_id' => $notification->organization_id,
                    'process_number' => $process->process_number,
                    'message' => $e->getMessage(),
                ]);

            throw $e;
        }
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Src\Application\Shared\Mail\ManualRegistrationCompletedMailable;
use Src\Domain\Process\Models\ManualRegistrationRequest;
use Src\Domain\Process\Models\Process;

/**
 * Notifies the requesting lawyer (AppUser) when ops completes a manual registration:
 * email + in-app database + WebSocket broadcast.
 */
class ManualRegistrationCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ManualRegistrationRequest $registrationRequest,
        private readonly ?Process $process = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return [
            'mail' => 'notifications-email',
            'database' => 'notifications',
            'broadcast' => 'notifications',
        ];
    }

    public function toMail(object $notifiable): ManualRegistrationCompletedMailable
    {
        $mailable = new ManualRegistrationCompletedMailable($this->registrationRequest, $this->process);

        /** @var string|array<int|string, string>|null $recipients */
        $recipients = $notifiable->routeNotificationFor('mail', $this);

        if (empty($recipients)) {
            return $mailable;
        }

        return $mailable->to($recipients);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->getData();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->getData()))->onQueue('notifications');
    }

    public function broadcastType(): string
    {
        return 'ManualRegistrationCompleted';
    }

    /**
     * @return array<string, mixed>
     */
    private function getData(): array
    {
        $number = $this->registrationRequest->process_number;

        return [
            'title' => __('process.manual_registration_completed_title'),
            'description' => __('process.manual_registration_completed_description', [
                'number' => $number,
            ]),
            'type' => 'manual-registration-completed',
            'request_id' => $this->registrationRequest->id,
            'process_id' => $this->process?->id,
            'process_number' => $number,
            'url' => $this->process instanceof Process
                ? '/gestion-procesos/'.$this->process->id
                : '/gestion-procesos',
        ];
    }
}

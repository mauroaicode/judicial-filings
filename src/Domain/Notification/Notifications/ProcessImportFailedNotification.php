<?php

declare(strict_types=1);

namespace Src\Domain\Notification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;


class ProcessImportFailedNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $processNumber,
        public string $error
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return [
            'database' => 'notifications',
            'broadcast' => 'notifications',
        ];
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => __('process.import_failed_title'),
            'description' => __('process.import_failed_description', [
                'number' => $this->processNumber,
                'error' => $this->error
            ]),
            'type' => 'process-import-failed',
            'id' => $this->processNumber, // Using process number as ID since we don't have a model ID
            'error' => $this->error,
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage([
            'title' => __('process.import_failed_title'),
            'description' => __('process.import_failed_description', [
                'number' => $this->processNumber,
                'error' => $this->error
            ]),
            'type' => 'process-import-failed',
            'id' => $this->processNumber,
            'error' => $this->error,
        ]))->onQueue('notifications');
    }

    /**
     * Get the type of the notification being broadcast.
     */
    public function broadcastType(): string
    {
        return 'ProcessImportFailed';
    }
}

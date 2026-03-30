<?php

declare(strict_types=1);

namespace Src\Domain\Notification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Src\Domain\Process\Models\Process;

class ProcessDataImportedNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    public function __construct(
        public Process $process
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
            'title' => __('process.data_imported_title'),
            'description' => __('process.data_imported_description', ['number' => $this->process->process_number]),
            'type' => 'process-imported',
            'id' => $this->process->id,
            'status' => $this->process->status,
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage([
            'title' => __('process.data_imported_title'),
            'description' => __('process.data_imported_description', ['number' => $this->process->process_number]),
            'type' => 'process-imported',
            'id' => $this->process->id,
            'status' => $this->process->status,
        ]))->onQueue('notifications');
    }

    /**
     * Get the type of the notification being broadcast.
     */
    public function broadcastType(): string
    {
        return 'ProcessDataImported';
    }
}

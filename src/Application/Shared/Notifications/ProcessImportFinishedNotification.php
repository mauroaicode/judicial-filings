<?php

declare(strict_types=1);

namespace Src\Application\Shared\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Src\Domain\Process\Models\ProcessImportBatch;

class ProcessImportFinishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ProcessImportBatch $batch
    ) {}

    /**
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
            'title' => __('process.import_finished_title'),
            'description' => __('process.import_finished_description', ['filename' => $this->batch->file_name]),
            'type' => 'import-report',
            'id' => $this->batch->id,
            'status' => $this->batch->status,
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage([
            'title' => __('process.import_finished_title'),
            'description' => __('process.import_finished_description', ['filename' => $this->batch->file_name]),
            'type' => 'import-report',
            'id' => $this->batch->id,
            'status' => $this->batch->status,
        ]))->onQueue('notifications');
    }

    /**
     * Get the type of the notification being broadcast.
     */
    public function broadcastType(): string
    {
        return 'ProcessImportFinished';
    }
}

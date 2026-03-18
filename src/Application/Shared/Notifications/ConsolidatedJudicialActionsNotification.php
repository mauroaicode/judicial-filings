<?php

declare(strict_types=1);

namespace Src\Application\Shared\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Src\Domain\Notification\Models\NotificationDigest;

class ConsolidatedJudicialActionsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly NotificationDigest $digest,
        private readonly int $count
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
     * Get the array representation for database storage.
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->getData();
    }

    /**
     * Get the broadcastable representation.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->getData()))->onQueue('notifications');
    }

    /**
     * Data structure for both DB and Broadcast.
     */
    private function getData(): array
    {
        return [
            'digest_id' => $this->digest->id,
            'title' => 'Resumen de actuaciones judiciales',
            'description' => "Se han detectado {$this->count} nuevas actuaciones en sus procesos seguidos.",
            'type' => 'consolidated-digest',
            'count' => $this->count,
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Get the type of the notification being broadcast.
     */
    public function broadcastType(): string
    {
        return 'ConsolidatedJudicialActions';
    }
}

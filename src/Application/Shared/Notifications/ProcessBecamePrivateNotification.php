<?php

declare(strict_types=1);

namespace Src\Application\Shared\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Src\Domain\Process\Models\Process;

/**
 * Notificación interna (in-app) y broadcast cuando un proceso pasa de público a privado
 * en Rama Judicial y el sistema inicia la migración hacia SAMAI.
 *
 * Se envía a todos los usuarios de las organizaciones que tienen el proceso activo,
 * independientemente de sus canales configurados (siempre llega in-app y broadcast).
 */
class ProcessBecamePrivateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Process $process,
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
        return 'ProcessBecamePrivate';
    }

    /**
     * @return array<string, mixed>
     */
    private function getData(): array
    {
        return [
            'title' => __('process.became_private_title'),
            'description' => __('process.became_private_description', [
                'number' => $this->process->process_number,
                'court' => $this->process->court,
            ]),
            'type' => 'process-became-private',
            'process_id' => $this->process->id,
            'process_number' => $this->process->process_number,
        ];
    }
}

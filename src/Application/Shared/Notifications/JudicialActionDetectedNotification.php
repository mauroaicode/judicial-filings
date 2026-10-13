<?php

declare(strict_types=1);

namespace Src\Application\Shared\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

class JudicialActionDetectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $notificationType  'actuacion' | 'actuacion_alerta' | 'actuacion_documento'
     */
    public function __construct(
        private readonly ProcessAction $action,
        private readonly Process $process,
        private readonly string $notificationType,
        private readonly ?int $mediaId = null,
        private readonly ?string $originalFilename = null,
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
        return $this->getData();
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->getData()))->onQueue('notifications');
    }

    /**
     * Data structure for both DB and Broadcast.
     *
     * @return array<string, mixed>
     */
    private function getData(): array
    {
        [$title, $description, $type] = match ($this->notificationType) {
            'actuacion_alerta' => [
                __('process.internal_alert_title'),
                __('process.internal_alert_description', ['number' => $this->process->process_number]),
                'alert-keyword',
            ],
            'actuacion_documento' => [
                __('process.internal_document_title'),
                __('process.internal_document_description', [
                    'filename' => $this->originalFilename ?: __('process.document_fallback_name'),
                    'number' => $this->process->process_number,
                ]),
                'action-documents',
            ],
            default => [
                __('process.internal_action_title'),
                __('process.internal_action_description', ['number' => $this->process->process_number]),
                'new-action',
            ],
        };

        $payload = [
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'process_id' => $this->process->id,
            'action_id' => $this->action->id,
            'process_number' => $this->process->process_number,
        ];

        if ($this->notificationType === 'actuacion_documento') {
            $payload['media_id'] = $this->mediaId;
            $payload['original_filename'] = $this->originalFilename;
        }

        return $payload;
    }

    /**
     * Get the type of the notification being broadcast.
     */
    public function broadcastType(): string
    {
        return 'JudicialActionDetected';
    }
}

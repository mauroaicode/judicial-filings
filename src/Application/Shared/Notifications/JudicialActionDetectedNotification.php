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
     * @param ProcessAction $action
     * @param Process $process
     * @param string $notificationType 'actuacion' | 'actuacion_alerta'
     */
    public function __construct(
        private readonly ProcessAction $action,
        private readonly Process $process,
        private readonly string $notificationType
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
        $isAlert = $this->notificationType === 'actuacion_alerta';
        
        return [
            'title' => $isAlert ? __('process.internal_alert_title') : __('process.internal_action_title'),
            'description' => $isAlert 
                ? __('process.internal_alert_description', ['number' => $this->process->process_number])
                : __('process.internal_action_description', ['number' => $this->process->process_number]),
            'type' => $isAlert ? 'alert-keyword' : 'new-action',
            'process_id' => $this->process->id,
            'action_id' => $this->action->id,
            'process_number' => $this->process->process_number,
        ];
    }

    /**
     * Get the type of the notification being broadcast.
     */
    public function broadcastType(): string
    {
        return 'JudicialActionDetected';
    }
}

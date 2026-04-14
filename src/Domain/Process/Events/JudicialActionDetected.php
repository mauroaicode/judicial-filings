<?php

declare(strict_types=1);

namespace Src\Domain\Process\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Src\Domain\Process\Models\ProcessAction;

class JudicialActionDetected implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $broadcastQueue = 'notifications';

    public function __construct(
        public ProcessAction $action,
        public string $organizationId,
        public string $notificationType
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('organization.'.$this->organizationId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'JudicialActionDetected';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action_id' => $this->action->id,
            'process_id' => $this->action->process_id,
            'process_number' => $this->action->process->process_number ?? null,
            'notification_type' => $this->notificationType,
            'annotation' => $this->action->annotation,
            'action_text' => $this->action->action,
            'date' => $this->action->action_date->format('Y-m-d'),
            'term_start_date' => $this->action->start_date ? $this->action->start_date->format('Y-m-d') : null,
            'term_end_date' => $this->action->end_date ? $this->action->end_date->format('Y-m-d') : null,
        ];
    }
}

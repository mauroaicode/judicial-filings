<?php

declare(strict_types=1);

namespace Src\Domain\Process\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Src\Domain\Process\Models\ProcessImportBatch;

class ProcessImportFinished implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public ProcessImportBatch $batch
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('organization.'.$this->batch->organization_id),
            new PrivateChannel('user.'.$this->batch->requested_by),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ProcessImportFinished';
    }

    public function broadcastQueue(): string
    {
        return config('process-import.jobs.import_radicado');
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'batch_id' => $this->batch->id,
            'status' => $this->batch->status,
            'success_count' => $this->batch->success_count,
            'failed_count' => $this->batch->failed_count,
            'total_count' => $this->batch->total_count,
            'completed_at' => $this->batch->completed_at?->toIso8601String(),
        ];
    }
}

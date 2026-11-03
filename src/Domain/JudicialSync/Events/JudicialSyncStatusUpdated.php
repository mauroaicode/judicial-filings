<?php

declare(strict_types=1);

namespace Src\Domain\JudicialSync\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;

/**
 * Live sync progress for the admin header (Reverb / Echo).
 *
 * Channel: private `admin.judicial-sync`
 * Event name: `JudicialSyncStatusUpdated`
 */
class JudicialSyncStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $runId,
        public JudicialSyncDataSource $dataSource,
        public JudicialSyncRunStatus $status,
        public int $processesQueued,
        public ?int $queuePendingJobs,
        public ?int $failedJobsCount,
        public bool $isActive,
        public ?string $laravelBatchId = null,
        public ?string $startedAt = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.judicial-sync'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'JudicialSyncStatusUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->runId,
            'data_source' => $this->dataSource->value,
            'data_source_label' => $this->dataSource->getLabel(),
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'is_active' => $this->isActive,
            'processes_queued' => $this->processesQueued,
            'queue_pending_jobs' => $this->queuePendingJobs,
            'failed_jobs_count' => $this->failedJobsCount,
            'laravel_batch_id' => $this->laravelBatchId,
            'started_at' => $this->startedAt,
        ];
    }
}

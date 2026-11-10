<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;
use Src\Domain\JudicialSync\Events\JudicialSyncStatusUpdated;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;

/**
 * Emits live sync status to admin clients via Reverb.
 * Progress updates are throttled to avoid flooding the WebSocket.
 */
readonly class JudicialSyncLiveStatusBroadcaster
{
    public function broadcast(JudicialSyncRun $run, ?Batch $batch = null, bool $force = false): void
    {
        if (! $force && ! $this->shouldEmitProgress($run)) {
            return;
        }

        $pending = null;
        $failed = $run->failed_jobs_count;

        if ($batch instanceof Batch) {
            $pending = (int) $batch->pendingJobs;
            $failed = (int) $batch->failedJobs;
        } elseif (isset($run->queue_pending_jobs)) {
            $pending = (int) $run->queue_pending_jobs;
        }

        $isActive = in_array($run->status, [
            JudicialSyncRunStatus::Started,
            JudicialSyncRunStatus::BatchPending,
        ], true);

        $run->loadMissing('organization');

        $this->publish(new JudicialSyncStatusUpdated(
            runId: $run->id,
            dataSource: $run->data_source,
            status: $run->status,
            processesQueued: (int) $run->processes_queued,
            queuePendingJobs: $pending,
            failedJobsCount: $failed,
            isActive: $isActive,
            laravelBatchId: $run->laravel_batch_id,
            startedAt: $run->started_at->toIso8601String(),
            organizationId: $run->organization_id,
            organizationName: $run->organization?->name,
        ));
    }

    private function publish(JudicialSyncStatusUpdated $event): void
    {
        try {
            event($event);
        } catch (BroadcastException $exception) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->warning('JudicialSyncLiveStatusBroadcaster: live status was not published', [
                    'run_id' => $event->runId,
                    'message' => $exception->getMessage(),
                ]);
        }
    }

    public function broadcastProgress(JudicialSyncRun $run, Batch $batch): void
    {
        $this->broadcast($run, $batch, force: false);
    }

    private function shouldEmitProgress(JudicialSyncRun $run): bool
    {
        $seconds = max(1, (int) config('judicial-sync.live_status.broadcast_throttle_seconds', 2));
        $key = 'judicial-sync:live-status:'.$run->id;

        return Cache::add($key, true, $seconds);
    }
}

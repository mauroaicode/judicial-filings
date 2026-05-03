<?php

declare(strict_types=1);

namespace Src\Domain\JudicialSync\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;

/**
 * @extends Builder<JudicialSyncRun>
 */
class JudicialSyncRunQueryBuilder extends Builder
{
    /**
     * @return $this
     */
    public function orderedByStartedAtDesc(): self
    {
        $this->latest('started_at')->orderByDesc('id');

        return $this;
    }

    /**
     * Latest row first — used by admin judicial sync history.
     *
     * @return $this
     */
    public function orderedByCreatedAtDesc(): self
    {
        $this->latest('judicial_sync_runs.created_at')->orderByDesc('judicial_sync_runs.id');

        return $this;
    }

    /**
     * @return $this
     */
    public function whereStatusValue(string $status): self
    {
        return $this->where('judicial_sync_runs.status', $status);
    }

    /**
     * @return $this
     */
    public function whereStartedAtBetween(?string $from, ?string $to): self
    {
        if ($from !== null && $from !== '') {
            $this->whereDate('judicial_sync_runs.started_at', '>=', Date::parse($from)->format('Y-m-d'));
        }

        if ($to !== null && $to !== '') {
            $this->whereDate('judicial_sync_runs.started_at', '<=', Date::parse($to)->format('Y-m-d'));
        }

        return $this;
    }

    /**
     * Include pending job count from `job_batches` when a Laravel batch id exists.
     *
     * @return $this
     */
    public function withQueuePendingJobs(): self
    {
        $this->leftJoin('job_batches', 'job_batches.id', '=', 'judicial_sync_runs.laravel_batch_id')
            ->addSelect(
                'judicial_sync_runs.*',
                'job_batches.pending_jobs as queue_pending_jobs'
            );

        return $this;
    }
}

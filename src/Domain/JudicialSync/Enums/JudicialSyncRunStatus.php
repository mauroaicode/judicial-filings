<?php

declare(strict_types=1);

namespace Src\Domain\JudicialSync\Enums;

enum JudicialSyncRunStatus: string
{
    /** Record created; command has not finished its synchronous phase yet. */
    case Started = 'started';

    /** Command ran successfully but no radicados matched active subscriptions. */
    case NoProcesses = 'no_processes';

    /** Batch could not be dispatched (exception before queue). */
    case DispatchFailed = 'dispatch_failed';

    /** Laravel batch is queued / workers processing jobs on `judicial-sync`. */
    case BatchPending = 'batch_pending';

    /** All batch jobs finished without failures. */
    case BatchCompleted = 'batch_completed';

    /** Batch finished with one or more failed jobs. */
    case BatchCompletedWithFailures = 'batch_completed_with_failures';

    /** Batch was cancelled before completion. */
    case BatchCancelled = 'batch_cancelled';

    public function getLabel(): string
    {
        return __('enums.judicial_sync_run_status.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

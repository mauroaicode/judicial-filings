<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Illuminate\Support\Facades\DB;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Task\Models\Task;

class RestoreTaskService
{
    public function __construct(
        private readonly RecordTaskTimelineEventService $recordTaskTimelineEventService,
    ) {}

    /**
     * Restore a trashed task.
     */
    public function handle(string $id, ?string $organizationId = null): Task
    {
        $task = $this->findTrashedTask($id, $organizationId)->load('process');

        return DB::transaction(function () use ($task): Task {
            $task->restore();
            $restoredTask = $task->fresh()->load('process');

            $this->recordTaskTimelineEventService->handle($restoredTask, ProcessTimelineEventType::TASK_RESTORED);

            return $restoredTask;
        });
    }

    private function findTrashedTask(string $id, ?string $organizationId = null): Task
    {
        return Task::query()
            ->onlyTrashed()
            ->when($organizationId, fn ($q): \Src\Domain\Task\QueryBuilders\TaskQueryBuilder => $q->whereOrganization($organizationId))
            ->findOrFail($id);
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Illuminate\Support\Facades\DB;
use Src\Application\Shared\Process\Services\ActivateOrganizationProcessService;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Enums\TaskType;
use Src\Domain\Task\Models\Task;

class UpdateTaskStatusService
{
    public function __construct(
        private readonly ActivateOrganizationProcessService $activateOrganizationProcessService,
    ) {}

    /**
     * Update the status of a task.
     */
    public function handle(string $id, TaskStatus $status, ?string $organizationId = null): Task
    {
        $task = $this->findTask($id, $organizationId);

        return DB::transaction(function () use ($task, $status): Task {
            if ($task->status !== $status) {
                $task->update(['status' => $status]);
            }

            $this->reactivateProcessIfSuspensionCompleted($task->fresh(), $status);

            return $task->fresh()->load('process');
        });
    }

    private function findTask(string $id, ?string $organizationId = null): Task
    {
        return Task::query()
            ->when($organizationId, fn ($q): \Src\Domain\Task\QueryBuilders\TaskQueryBuilder => $q->whereOrganization($organizationId))
            ->findOrFail($id);
    }

    private function reactivateProcessIfSuspensionCompleted(Task $task, TaskStatus $status): void
    {
        if ($status !== TaskStatus::COMPLETED) {
            return;
        }

        if ($task->type !== TaskType::SUSPENSION || ! $task->process_id) {
            return;
        }

        $this->activateOrganizationProcessService->handle(
            $task->organization_id,
            $task->process_id,
        );
    }
}

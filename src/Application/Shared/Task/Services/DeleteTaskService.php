<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Illuminate\Support\Facades\DB;
use Src\Application\Shared\Process\Services\ActivateOrganizationProcessService;
use Src\Domain\Task\Enums\TaskType;
use Src\Domain\Task\Models\Task;

class DeleteTaskService
{
    public function __construct(
        private readonly ActivateOrganizationProcessService $activateOrganizationProcessService,
    ) {}

    /**
     * Move a task to the trash (soft delete).
     */
    public function handle(string $id, ?string $organizationId = null): void
    {
        $task = $this->findTask($id, $organizationId);

        DB::transaction(function () use ($task): void {
            $this->reactivateProcessIfSuspension($task);
            $task->delete();
        });
    }

    private function findTask(string $id, ?string $organizationId = null): Task
    {
        return Task::query()
            ->when($organizationId, fn ($q): \Src\Domain\Task\QueryBuilders\TaskQueryBuilder => $q->whereOrganization($organizationId))
            ->findOrFail($id);
    }

    private function reactivateProcessIfSuspension(Task $task): void
    {
        if ($task->type !== TaskType::SUSPENSION || ! $task->process_id) {
            return;
        }

        $this->activateOrganizationProcessService->handle(
            $task->organization_id,
            $task->process_id,
        );
    }
}

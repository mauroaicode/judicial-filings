<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Src\Application\Shared\Process\Services\SuspendOrganizationProcessService;
use Src\Application\Shared\Task\Support\TaskTimelineState;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Task\Data\TaskData;
use Src\Domain\Task\Enums\TaskType;
use Src\Domain\Task\Models\Task;

class UpdateTaskService
{
    public function __construct(
        private readonly SuspendOrganizationProcessService $suspendOrganizationProcessService,
        private readonly EnsureProcessHasNoActiveSuspensionTaskService $ensureProcessHasNoActiveSuspensionTaskService,
        private readonly RecordTaskTimelineEventService $recordTaskTimelineEventService,
    ) {}

    /**
     * Update a task by its ID with new data.
     */
    public function handle(string $id, TaskData $data, ?string $organizationId = null): Task
    {
        $task = $this->findTask($id, $organizationId);

        $this->validateRelations($data, $task);

        return DB::transaction(function () use ($task, $data): Task {
            $before = TaskTimelineState::capture($task);
            $this->updateTask($task, $data);
            $updatedTask = $task->fresh()->load('process');

            $this->recordTaskTimelineEventService->handle(
                $updatedTask,
                ProcessTimelineEventType::TASK_UPDATED,
                ['before' => $before],
            );
            $this->applySuspensionIfNeeded($data, $updatedTask);

            return $updatedTask;
        });
    }

    private function findTask(string $id, ?string $organizationId = null): Task
    {
        return Task::query()
            ->when($organizationId, fn ($q): \Src\Domain\Task\QueryBuilders\TaskQueryBuilder => $q->whereOrganization($organizationId))
            ->findOrFail($id);
    }

    private function validateRelations(TaskData $data, Task $task): void
    {
        $organization = Organization::query()->find($data->organization_id);

        if (! $organization) {
            throw ValidationException::withMessages([
                'organization_id' => [__('organization.not_found')],
            ]);
        }

        $type = TaskType::from($data->type ?? TaskType::GENERAL->value);

        if ($type === TaskType::SUSPENSION && ! $data->process_id) {
            throw ValidationException::withMessages([
                'process_id' => [__('process.suspension_requires_process')],
            ]);
        }

        if ($data->process_id) {
            $processExistsInOrganization = $organization->processes()
                ->where('processes.id', $data->process_id)
                ->exists();

            if (! $processExistsInOrganization) {
                throw ValidationException::withMessages([
                    'process_id' => [__('process.not_found_in_organization')],
                ]);
            }

            if ($type === TaskType::SUSPENSION) {
                $this->ensureProcessHasNoActiveSuspensionTaskService->handle(
                    $organization->id,
                    $data->process_id,
                    $task->id,
                );
            }
        }
    }

    private function updateTask(Task $task, TaskData $data): void
    {
        $attributes = [
            'title' => $data->title,
            'description' => $data->description,
            'type' => TaskType::from($data->type ?? TaskType::GENERAL->value),
            'due_date' => $data->due_date,
            'reminder_days' => $data->reminder_days,
            'is_admin' => $data->is_admin,
            'process_id' => $data->process_id,
            'organization_id' => $data->organization_id,
        ];

        if ($this->dueDateWasExtended($task, $data->due_date)) {
            $attributes['last_notified_urgency_level'] = null;
            $attributes['last_due_reminder_sent_on'] = null;
        }

        $task->update($attributes);
    }

    private function dueDateWasExtended(Task $task, string $newDueDate): bool
    {
        if ($task->due_date === null) {
            return false;
        }

        return Date::parse($newDueDate)->gt($task->due_date);
    }

    private function applySuspensionIfNeeded(TaskData $data, Task $task): void
    {
        $type = TaskType::from($data->type ?? TaskType::GENERAL->value);

        if ($type !== TaskType::SUSPENSION || ! $data->process_id || ! $data->organization_id) {
            return;
        }

        $this->suspendOrganizationProcessService->handle(
            $data->organization_id,
            $data->process_id,
            $task,
        );
    }
}

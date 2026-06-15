<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Illuminate\Validation\ValidationException;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Task\Data\TaskData;
use Src\Domain\Task\Models\Task;

class UpdateTaskService
{
    /**
     * Update a task by its ID with new data.
     */
    public function handle(string $id, TaskData $data, ?string $organizationId = null): Task
    {
        $task = $this->findTask($id, $organizationId);

        $this->validateRelations($data);

        $this->updateTask($task, $data);

        return $task->fresh()->load('process');
    }

    private function findTask(string $id, ?string $organizationId = null): Task
    {
        return Task::query()
            ->when($organizationId, fn ($q): \Src\Domain\Task\QueryBuilders\TaskQueryBuilder => $q->whereOrganization($organizationId))
            ->findOrFail($id);
    }

    private function validateRelations(TaskData $data): void
    {
        $organization = Organization::query()->find($data->organization_id);

        if (! $organization) {
            throw ValidationException::withMessages([
                'organization_id' => [__('organization.not_found')],
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
        }
    }

    private function updateTask(Task $task, TaskData $data): void
    {
        $task->update([
            'title' => $data->title,
            'description' => $data->description,
            'due_date' => $data->due_date,
            'reminder_days' => $data->reminder_days,
            'is_admin' => $data->is_admin,
            'process_id' => $data->process_id,
            'organization_id' => $data->organization_id,
        ]);
    }
}

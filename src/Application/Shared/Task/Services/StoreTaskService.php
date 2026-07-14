<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Src\Application\Shared\Process\Services\SuspendOrganizationProcessService;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Task\Data\TaskData;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Enums\TaskType;
use Src\Domain\Task\Models\Task;

class StoreTaskService
{
    public function __construct(
        private readonly SuspendOrganizationProcessService $suspendOrganizationProcessService,
        private readonly EnsureProcessHasNoActiveSuspensionTaskService $ensureProcessHasNoActiveSuspensionTaskService,
    ) {}

    public function handle(TaskData $data): Task
    {
        $this->validateRelations($data);

        return DB::transaction(function () use ($data): Task {
            $task = $this->createTask($data);

            $this->applySuspensionIfNeeded($data);

            return $task->load('process');
        });
    }

    private function validateRelations(TaskData $data): void
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
                );
            }
        }
    }

    private function createTask(TaskData $data): Task
    {
        return Task::query()->create([
            'title' => $data->title,
            'description' => $data->description,
            'type' => TaskType::from($data->type ?? TaskType::GENERAL->value),
            'due_date' => $data->due_date,
            'reminder_days' => $data->reminder_days,
            'status' => TaskStatus::PENDING,
            'is_admin' => $data->is_admin,
            'process_id' => $data->process_id,
            'organization_id' => $data->organization_id,
        ]);
    }

    private function applySuspensionIfNeeded(TaskData $data): void
    {
        $type = TaskType::from($data->type ?? TaskType::GENERAL->value);

        if ($type !== TaskType::SUSPENSION || ! $data->process_id || ! $data->organization_id) {
            return;
        }

        $this->suspendOrganizationProcessService->handle(
            $data->organization_id,
            $data->process_id,
        );
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Illuminate\Validation\ValidationException;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Task\Data\TaskData;
use Src\Domain\Task\Models\Task;

class StoreTaskService
{
    public function handle(TaskData $data): Task
    {
        $this->validateRelations($data);

        return $this->createTask($data)->load('process');
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

    private function createTask(TaskData $data): Task
    {
        return Task::query()->create([
            'title' => $data->title,
            'description' => $data->description,
            'is_admin' => $data->is_admin,
            'process_id' => $data->process_id,
            'organization_id' => $data->organization_id,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Application\AppUser\Organization\Services\ResolveUserOrganizationService;
use Src\Application\Shared\Task\Resources\TaskResource;
use Src\Application\Shared\Task\Services\DeleteTaskService;
use Src\Application\Shared\Task\Services\ListTasksService;
use Src\Application\Shared\Task\Services\ShowTaskService;
use Src\Application\Shared\Task\Services\StoreTaskService;
use Src\Application\Shared\Task\Services\UpdateTaskService;
use Src\Domain\Task\Data\TaskData;
use Src\Domain\Task\Models\Task;

class TaskController
{
    public function __construct(
        private readonly ResolveUserOrganizationService $resolveUserOrganizationService
    ) {}

    /**
     * Display a listing of tasks.
     */
    public function index(Request $request, ListTasksService $service): JsonResponse
    {
        $filters = $request->all();

        if (($organization = $this->resolveUserOrganizationService->handle()) instanceof \Src\Domain\Organization\Models\Organization) {
            $filters['organization_id'] = $organization->id;
            $filters['is_admin'] = false;
        }

        $tasks = $service->handle($filters);

        $tasks->through(fn (Task $task): TaskResource => TaskResource::fromModel($task));

        return response()->json($tasks);
    }

    /**
     * Store a newly created task.
     */
    public function store(TaskData $data, StoreTaskService $service): JsonResponse
    {
        if (($organization = $this->resolveUserOrganizationService->handle()) instanceof \Src\Domain\Organization\Models\Organization) {
            $data->organization_id = $organization->id;
            $data->is_admin = false;
        }

        $task = $service->handle($data);

        return response()->json(TaskResource::fromModel($task), 201);
    }

    /**
     * Display the specified task.
     */
    public function show(string $id, ShowTaskService $service): JsonResponse
    {
        $organization = $this->resolveUserOrganizationService->handle();

        $task = $service->handle($id, $organization?->id);

        return response()->json(TaskResource::fromModel($task));
    }

    /**
     * Update the specified task.
     */
    public function update(string $id, TaskData $data, UpdateTaskService $service): JsonResponse
    {
        $organization = $this->resolveUserOrganizationService->handle();

        if ($organization instanceof \Src\Domain\Organization\Models\Organization) {
            $data->organization_id = $organization->id;
            $data->is_admin = false;
        }

        $updatedTask = $service->handle($id, $data, $organization?->id);

        return response()->json(TaskResource::fromModel($updatedTask));
    }

    /**
     * Remove the specified task from storage.
     */
    public function destroy(string $id, DeleteTaskService $service): JsonResponse
    {
        $organization = $this->resolveUserOrganizationService->handle();

        $service->handle($id, $organization?->id);

        return response()->json(null, 204);
    }
}

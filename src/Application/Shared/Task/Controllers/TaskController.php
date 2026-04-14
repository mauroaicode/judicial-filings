<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    /**
     * Display a listing of tasks.
     */
    public function index(Request $request, ListTasksService $service): JsonResponse
    {
        $tasks = $service->handle($request->all());

        $tasks->through(fn (Task $task): \Src\Application\Shared\Task\Resources\TaskResource => TaskResource::fromModel($task));

        return response()->json($tasks);
    }

    /**
     * Store a newly created task.
     */
    public function store(TaskData $data, StoreTaskService $service): JsonResponse
    {
        $task = $service->handle($data);

        return response()->json(TaskResource::fromModel($task), 201);
    }

    /**
     * Display the specified task.
     */
    public function show(string $id, ShowTaskService $service): JsonResponse
    {
        $task = $service->handle($id);

        return response()->json(TaskResource::fromModel($task));
    }

    /**
     * Update the specified task.
     */
    public function update(string $id, TaskData $data, UpdateTaskService $service): JsonResponse
    {
        $updatedTask = $service->handle($id, $data);

        return response()->json(TaskResource::fromModel($updatedTask));
    }

    /**
     * Remove the specified task from storage.
     */
    public function destroy(string $id, DeleteTaskService $service): JsonResponse
    {
        $service->handle($id);

        return response()->json(null, 204);
    }
}

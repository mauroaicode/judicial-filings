<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\AppUser\Organization\Services\ResolveUserOrganizationService;
use Src\Application\Shared\Task\Data\ListProcessTasksFilterData;
use Src\Application\Shared\Task\Resources\TaskResource;
use Src\Application\Shared\Task\Services\ListProcessTasksService;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Task\Models\Task;

class ProcessTaskController
{
    public function __construct(
        private readonly ResolveUserOrganizationService $resolveUserOrganizationService,
        private readonly ListProcessTasksService $listProcessTasksService,
    ) {}

    /**
     * List tasks related to a process for the authenticated user's organization.
     */
    public function index(string $processId, ListProcessTasksFilterData $filters): JsonResponse
    {
        $organization = $this->resolveUserOrganizationService->handle();

        if (! $organization instanceof Organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $tasks = $this->listProcessTasksService->handle(
            $processId,
            $organization->id,
            $filters,
        );

        $tasks->through(fn (Task $task): TaskResource => TaskResource::fromModel($task));

        return response()->json($tasks);
    }
}

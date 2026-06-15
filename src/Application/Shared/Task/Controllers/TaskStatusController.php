<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Domain\Task\Enums\TaskStatus;

class TaskStatusController
{
    /**
     * Get all task statuses.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(TaskStatus::toArray());
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Src\Domain\Task\Models\Task;

class ShowTaskService
{
    /**
     * Get a specific task by its ID.
     */
    public function handle(string $id): Task
    {
        return $this->findTask($id);
    }

    private function findTask(string $id): Task
    {
        return Task::query()->with('process')->findOrFail($id);
    }
}

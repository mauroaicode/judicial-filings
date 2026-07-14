<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Illuminate\Validation\ValidationException;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Enums\TaskType;
use Src\Domain\Task\Models\Task;

class EnsureProcessHasNoActiveSuspensionTaskService
{
    /**
     * Prevent linking a process to more than one active suspension task per organization.
     */
    public function handle(
        string $organizationId,
        string $processId,
        ?string $ignoreTaskId = null,
    ): void {
        $exists = Task::query()
            ->whereOrganization($organizationId)
            ->whereProcess($processId)
            ->whereType(TaskType::SUSPENSION)
            ->whereIn('status', [
                TaskStatus::PENDING->value,
                TaskStatus::DRAFT->value,
            ])
            ->when(
                $ignoreTaskId !== null,
                fn ($query) => $query->whereKeyNot($ignoreTaskId),
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'process_id' => [__('process.already_has_suspension_task')],
            ]);
        }
    }
}

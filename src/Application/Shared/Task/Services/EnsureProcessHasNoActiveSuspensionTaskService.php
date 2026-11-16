<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Illuminate\Validation\ValidationException;
use Src\Application\Shared\Process\Services\ResolveOrganizationProcessIdsForRadicadoService;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Models\Task;

class EnsureProcessHasNoActiveSuspensionTaskService
{
    public function __construct(
        private readonly ResolveOrganizationProcessIdsForRadicadoService $resolveOrganizationProcessIdsForRadicadoService,
    ) {}

    /**
     * Prevent linking a radicado to more than one active pausing task per organization
     * (any sibling instance of the same process_number counts).
     */
    public function handle(
        string $organizationId,
        string $processId,
        ?string $ignoreTaskId = null,
    ): void {
        $processIds = $this->resolveOrganizationProcessIdsForRadicadoService->handle(
            $organizationId,
            [$processId],
        );

        if ($processIds === []) {
            $processIds = [$processId];
        }

        $exists = Task::query()
            ->whereOrganization($organizationId)
            ->whereIn('process_id', $processIds)
            ->wherePausesProcess()
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

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Services;

use Illuminate\Validation\ValidationException;
use Src\Application\Shared\Process\Timeline\Contracts\ProcessTimelineRecorder;
use Src\Application\Shared\Process\Timeline\DTOs\RecordProcessTimelineEventData;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Task\Enums\TaskType;
use Src\Domain\Task\Models\Task;

readonly class SuspendOrganizationProcessService
{
    public function __construct(
        private ProcessTimelineRecorder $timelineRecorder,
        private ResolveOrganizationProcessIdsForRadicadoService $resolveOrganizationProcessIdsForRadicadoService,
    ) {}

    /**
     * Pause inactivity tracking for the selected process and every sibling
     * instance of the same radicado linked to the organization.
     */
    public function handle(string $organizationId, string $processId, ?Task $cause = null): void
    {
        $processIds = $this->resolveOrganizationProcessIdsForRadicadoService->handle(
            $organizationId,
            [$processId],
        );

        if ($processIds === []) {
            throw ValidationException::withMessages([
                'process_id' => [__('process.relationship_not_found')],
            ]);
        }

        $targetStatus = $this->resolveTargetStatus($cause);

        foreach ($processIds as $siblingProcessId) {
            $this->pauseSingle($organizationId, $siblingProcessId, $targetStatus, $cause);
        }
    }

    private function pauseSingle(
        string $organizationId,
        string $processId,
        OrganizationProcessStatus $targetStatus,
        ?Task $cause,
    ): void {
        $organizationProcess = OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->where('process_id', $processId)
            ->first();

        if (! $organizationProcess instanceof OrganizationProcess) {
            return;
        }

        $previousStatus = OrganizationProcessStatus::fromPivot($organizationProcess);
        $previousIsActive = $organizationProcess->is_active;

        OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->where('process_id', $processId)
            ->update([
                'status' => $targetStatus->value,
                'is_active' => true,
                'updated_at' => now(),
            ]);

        if ($previousStatus === $targetStatus) {
            return;
        }

        $process = $organizationProcess->process;
        $causeKey = $cause->id ?? now()->format('U.u');

        $this->timelineRecorder->handle($process, new RecordProcessTimelineEventData(
            eventType: ProcessTimelineEventType::PROCESS_SUSPENDED,
            source: $cause instanceof Task ? ProcessTimelineEventSource::USER : ProcessTimelineEventSource::SYSTEM,
            idempotencyKey: "process-suspended:{$organizationId}:{$processId}:{$targetStatus->value}:{$causeKey}",
            payload: [
                'from' => ['status' => $previousStatus->value, 'is_active' => $previousIsActive],
                'to' => ['status' => $targetStatus->value, 'is_active' => true],
                'reason' => 'suspension_task_created_or_updated',
                'task_type' => $cause?->type instanceof TaskType ? $cause->type->value : null,
                'semaphore_paused' => true,
                'expanded_to_radicado_instances' => true,
            ],
            organizationId: $organizationId,
            subjectType: $cause instanceof Task ? 'task' : null,
            subjectId: $cause?->id,
        ));
    }

    private function resolveTargetStatus(?Task $cause): OrganizationProcessStatus
    {
        $type = $cause?->type;

        if ($type instanceof TaskType && $type->pausesProcess()) {
            return $type->toOrganizationProcessStatus();
        }

        return OrganizationProcessStatus::SUSPENDED;
    }
}

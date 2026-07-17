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
use Src\Domain\Task\Models\Task;

readonly class SuspendOrganizationProcessService
{
    public function __construct(
        private ProcessTimelineRecorder $timelineRecorder,
    ) {}

    /**
     * Mark the organization–process relationship as suspended.
     */
    public function handle(string $organizationId, string $processId, ?Task $cause = null): void
    {
        $organizationProcess = OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->where('process_id', $processId)
            ->first();

        if (! $organizationProcess instanceof OrganizationProcess) {
            throw ValidationException::withMessages([
                'process_id' => [__('process.relationship_not_found')],
            ]);
        }

        $previousStatus = OrganizationProcessStatus::fromPivot($organizationProcess);
        $previousIsActive = $organizationProcess->is_active;

        OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->where('process_id', $processId)
            ->update([
                'status' => OrganizationProcessStatus::SUSPENDED->value,
                'is_active' => true,
                'updated_at' => now(),
            ]);

        if ($previousStatus === OrganizationProcessStatus::SUSPENDED) {
            return;
        }

        $process = $organizationProcess->process;
        $causeKey = $cause->id ?? now()->format('U.u');

        $this->timelineRecorder->handle($process, new RecordProcessTimelineEventData(
            eventType: ProcessTimelineEventType::PROCESS_SUSPENDED,
            source: $cause instanceof Task ? ProcessTimelineEventSource::USER : ProcessTimelineEventSource::SYSTEM,
            idempotencyKey: "process-suspended:{$organizationId}:{$processId}:{$causeKey}",
            payload: [
                'from' => ['status' => $previousStatus->value, 'is_active' => $previousIsActive],
                'to' => ['status' => OrganizationProcessStatus::SUSPENDED->value, 'is_active' => true],
                'reason' => 'suspension_task_created_or_updated',
                'semaphore_paused' => true,
            ],
            organizationId: $organizationId,
            subjectType: $cause instanceof Task ? 'task' : null,
            subjectId: $cause?->id,
        ));
    }
}

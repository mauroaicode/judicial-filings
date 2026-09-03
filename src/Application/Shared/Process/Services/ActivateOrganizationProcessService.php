<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Services;

use Illuminate\Validation\ValidationException;
use Src\Application\Shared\Process\Timeline\Contracts\ProcessTimelineRecorder;
use Src\Application\Shared\Process\Timeline\DTOs\RecordProcessTimelineEventData;
use Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Task\Models\Task;

readonly class ActivateOrganizationProcessService
{
    public function __construct(
        private ProcessTimelineRecorder $timelineRecorder,
        private OrganizationProcessQuotaService $organizationProcessQuotaService,
    ) {}

    /**
     * Mark the organization–process relationship as active again.
     */
    public function handle(
        string $organizationId,
        string $processId,
        ?Task $cause = null,
        string $reason = 'suspension_ended',
    ): void {
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

        if ($previousStatus !== OrganizationProcessStatus::ACTIVE) {
            $this->organizationProcessQuotaService->assertCanActivateProcess($organizationId, $processId);
        }

        OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->where('process_id', $processId)
            ->update([
                'status' => OrganizationProcessStatus::ACTIVE->value,
                'is_active' => true,
                'updated_at' => now(),
            ]);

        if ($previousStatus !== OrganizationProcessStatus::SUSPENDED) {
            return;
        }

        $process = $organizationProcess->process;
        $causeKey = $cause->id ?? now()->format('U.u');

        $this->timelineRecorder->handle($process, new RecordProcessTimelineEventData(
            eventType: ProcessTimelineEventType::PROCESS_RESUMED,
            source: $cause instanceof Task ? ProcessTimelineEventSource::USER : ProcessTimelineEventSource::SYSTEM,
            idempotencyKey: "process-resumed:{$organizationId}:{$processId}:{$reason}:{$causeKey}",
            payload: [
                'from' => ['status' => $previousStatus->value],
                'to' => ['status' => OrganizationProcessStatus::ACTIVE->value],
                'reason' => $reason,
                'semaphore_paused' => false,
            ],
            organizationId: $organizationId,
            subjectType: $cause instanceof Task ? 'task' : null,
            subjectId: $cause?->id,
        ));
    }
}

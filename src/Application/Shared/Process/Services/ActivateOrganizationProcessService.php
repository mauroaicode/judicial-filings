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
        private ResolveOrganizationProcessIdsForRadicadoService $resolveOrganizationProcessIdsForRadicadoService,
    ) {}

    /**
     * Resume inactivity tracking for the selected process and every paused
     * sibling instance of the same radicado linked to the organization.
     */
    public function handle(
        string $organizationId,
        string $processId,
        ?Task $cause = null,
        string $reason = 'suspension_ended',
    ): void {
        $processIds = $this->resolveOrganizationProcessIdsForRadicadoService->handle(
            $organizationId,
            [$processId],
        );

        if ($processIds === []) {
            throw ValidationException::withMessages([
                'process_id' => [__('process.relationship_not_found')],
            ]);
        }

        foreach ($processIds as $siblingProcessId) {
            $this->resumeSingle($organizationId, $siblingProcessId, $cause, $reason);
        }
    }

    private function resumeSingle(
        string $organizationId,
        string $processId,
        ?Task $cause,
        string $reason,
    ): void {
        $organizationProcess = OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->where('process_id', $processId)
            ->first();

        if (! $organizationProcess instanceof OrganizationProcess) {
            return;
        }

        $previousStatus = OrganizationProcessStatus::fromPivot($organizationProcess);

        if (! $previousStatus->isPaused()) {
            return;
        }

        $this->organizationProcessQuotaService->assertCanActivateProcess($organizationId, $processId);

        OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->where('process_id', $processId)
            ->update([
                'status' => OrganizationProcessStatus::ACTIVE->value,
                'is_active' => true,
                'updated_at' => now(),
            ]);

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
                'expanded_to_radicado_instances' => true,
            ],
            organizationId: $organizationId,
            subjectType: $cause instanceof Task ? 'task' : null,
            subjectId: $cause?->id,
        ));
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Src\Application\Shared\Process\Timeline\Contracts\ProcessTimelineRecorder;
use Src\Application\Shared\Process\Timeline\DTOs\RecordProcessTimelineEventData;
use Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;

readonly class ToggleProcessStatusService
{
    public function __construct(
        private ProcessTimelineRecorder $timelineRecorder,
        private OrganizationProcessQuotaService $organizationProcessQuotaService,
    ) {}

    /**
     * Toggle the active status of a process for the given organization.
     *
     * @param  string  $processId  The process UUID.
     * @param  string  $organizationId  The organization UUID.
     * @param  bool  $isActive  The desired active status.
     */
    public function handle(string $processId, string $organizationId, bool $isActive): void
    {
        $process = $this->findProcessForOrganization($processId, $organizationId);

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $organizationProcess = $this->findOrganizationProcess($organizationId, $processId);
        $previousStatus = OrganizationProcessStatus::fromPivot($organizationProcess);
        $previousIsActive = $organizationProcess->is_active;
        $targetStatus = OrganizationProcessStatus::fromBoolean($isActive);

        if ($previousStatus === $targetStatus && $previousIsActive === $isActive) {
            return;
        }

        if ($isActive) {
            $this->organizationProcessQuotaService->assertCanActivateProcess($organizationId, $processId);
        }

        DB::transaction(function () use ($process, $organizationProcess, $organizationId, $isActive, $previousStatus, $previousIsActive, $targetStatus): void {
            $this->updatePivotStatus($organizationProcess, $isActive, $targetStatus);
            $occurredAt = now();

            $this->timelineRecorder->handle($process, new RecordProcessTimelineEventData(
                eventType: $isActive
                    ? ProcessTimelineEventType::TRACKING_ACTIVATED
                    : ProcessTimelineEventType::TRACKING_DEACTIVATED,
                source: ProcessTimelineEventSource::USER,
                idempotencyKey: "tracking:{$organizationId}:{$process->id}:{$targetStatus->value}:{$occurredAt->format('U.u')}",
                payload: [
                    'from' => ['status' => $previousStatus->value, 'is_active' => $previousIsActive],
                    'to' => ['status' => $targetStatus->value, 'is_active' => $isActive],
                ],
                organizationId: $organizationId,
                subjectType: 'process',
                subjectId: $process->id,
                occurredAt: $occurredAt,
            ));
        });
    }

    /**
     * Find a process by ID that belongs to the given organization.
     */
    private function findProcessForOrganization(string $processId, string $organizationId): ?Process
    {
        return Process::query()
            ->where('id', $processId)
            ->whereOrganization($organizationId)
            ->first();
    }

    private function findOrganizationProcess(string $organizationId, string $processId): OrganizationProcess
    {
        $organizationProcess = OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->where('process_id', $processId)
            ->firstOrFail();

        if (OrganizationProcessStatus::fromPivot($organizationProcess) === OrganizationProcessStatus::SUSPENDED) {
            throw ValidationException::withMessages([
                'is_active' => [__('process.cannot_toggle_while_suspended')],
            ]);
        }

        return $organizationProcess;
    }

    /**
     * Update the is_active flag and status on the organization_processes pivot.
     */
    private function updatePivotStatus(
        OrganizationProcess $organizationProcess,
        bool $isActive,
        OrganizationProcessStatus $status,
    ): void {
        OrganizationProcess::query()
            ->where('organization_id', $organizationProcess->organization_id)
            ->where('process_id', $organizationProcess->process_id)
            ->update([
                'is_active' => $isActive,
                'status' => $status->value,
                'updated_at' => now(),
            ]);
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Src\Application\Admin\Process\Data\AttachAdminProcessOrganizationsData;
use Src\Application\Shared\Process\Timeline\Contracts\ProcessTimelineRecorder;
use Src\Application\Shared\Process\Timeline\DTOs\RecordProcessTimelineEventData;
use Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;

readonly class AttachAdminProcessOrganizationsService
{
    public function __construct(
        private OrganizationProcessQuotaService $organizationProcessQuotaService,
        private ProcessTimelineRecorder $timelineRecorder,
    ) {}

    public function handle(
        string $processId,
        AttachAdminProcessOrganizationsData $data,
        ?string $actorId = null,
    ): Process {
        $process = Process::query()->find($processId);

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $organizationIds = array_values(array_unique($data->organization_ids));
        $organizations = Organization::query()
            ->whereIn('id', $organizationIds)
            ->get()
            ->keyBy('id');

        $this->assertOrganizationsAreAttachable($organizationIds, $organizations, $process);

        DB::transaction(function () use ($process, $organizationIds, $organizations, $actorId): void {
            foreach ($organizationIds as $organizationId) {
                $this->attachOrganization($process, $organizations->get($organizationId), $actorId);
            }
        });

        return $process->refresh()->load('organizations');
    }

    /**
     * @param  list<string>  $organizationIds
     * @param  Collection<string, Organization>  $organizations
     */
    private function assertOrganizationsAreAttachable(
        array $organizationIds,
        Collection $organizations,
        Process $process,
    ): void {
        $errors = [];

        foreach ($organizationIds as $index => $organizationId) {
            $organization = $organizations->get($organizationId);

            if (! $organization instanceof Organization) {
                $errors["organization_ids.{$index}"] = [__('process.organization_not_found', ['id' => $organizationId])];

                continue;
            }

            if (! $organization->is_active) {
                $errors["organization_ids.{$index}"] = [__('process.organization_inactive')];

                continue;
            }

            if ($this->hasActiveLink($organizationId, $process->id)) {
                continue;
            }

            if (
                $this->organizationProcessQuotaService->wouldConsumeNewActiveSlot($organizationId, $process->process_number)
                && ! $this->organizationProcessQuotaService->canAddProcesses($organizationId)
            ) {
                $errors["organization_ids.{$index}"] = [
                    __('process.max_active_processes_reached', [
                        'limit' => $this->organizationProcessQuotaService->resolveLimit($organizationId) ?? 0,
                        'current' => $this->organizationProcessQuotaService->countActiveProcesses($organizationId),
                    ]),
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function attachOrganization(Process $process, ?Organization $organization, ?string $actorId): void
    {
        if (! $organization instanceof Organization) {
            return;
        }

        $alreadyActive = $this->hasActiveLink($organization->id, $process->id);

        OrganizationProcess::syncActiveLink($organization->id, $process->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
            'status' => OrganizationProcessStatus::ACTIVE,
        ]);

        if ($alreadyActive) {
            return;
        }

        $occurredAt = now();

        $this->timelineRecorder->handle($process, new RecordProcessTimelineEventData(
            eventType: ProcessTimelineEventType::TRACKING_ACTIVATED,
            source: ProcessTimelineEventSource::USER,
            idempotencyKey: "tracking-attached:{$organization->id}:{$process->id}:{$occurredAt->format('U.u')}",
            payload: [
                'from' => ['status' => null, 'is_active' => false],
                'to' => ['status' => OrganizationProcessStatus::ACTIVE->value, 'is_active' => true],
                'reason' => 'admin_attached_organization',
            ],
            organizationId: $organization->id,
            subjectType: 'process',
            subjectId: $process->id,
            actorType: 'admin',
            actorId: $actorId,
            occurredAt: $occurredAt,
        ));
    }

    private function hasActiveLink(string $organizationId, string $processId): bool
    {
        return OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->where('process_id', $processId)
            ->whereNull('deleted_at')
            ->exists();
    }
}

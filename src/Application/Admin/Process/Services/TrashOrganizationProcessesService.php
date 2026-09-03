<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Support\Facades\DB;
use Src\Application\Shared\Process\Timeline\Contracts\ProcessTimelineRecorder;
use Src\Application\Shared\Process\Timeline\DTOs\RecordProcessTimelineEventData;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;

readonly class TrashOrganizationProcessesService
{
    public function __construct(
        private ProcessTimelineRecorder $timelineRecorder,
    ) {}

    /**
     * Soft-delete organization↔process links (per-org trash). Does not delete the process master.
     *
     * @param  list<string>  $processIds
     * @return array{
     *     trashed_count: int,
     *     trashed_ids: list<string>,
     *     skipped: list<array{process_id: string, reason: string}>
     * }
     */
    public function handle(string $organizationId, array $processIds, ?string $deletedBy = null): array
    {
        $uniqueIds = array_values(array_unique($processIds));

        return DB::transaction(function () use ($organizationId, $uniqueIds, $deletedBy): array {
            $links = OrganizationProcess::query()
                ->with('process')
                ->where('organization_id', $organizationId)
                ->whereIn('process_id', $uniqueIds)
                ->get()
                ->keyBy('process_id');

            $trashedIds = [];
            $skipped = [];

            foreach ($uniqueIds as $processId) {
                $link = $links->get($processId);

                if (! $link instanceof OrganizationProcess) {
                    $alreadyTrashed = OrganizationProcess::onlyTrashed()
                        ->where('organization_id', $organizationId)
                        ->where('process_id', $processId)
                        ->exists();

                    $skipped[] = [
                        'process_id' => $processId,
                        'reason' => $alreadyTrashed ? 'already_trashed' : 'not_linked',
                    ];

                    continue;
                }

                $this->trashLink($link, $organizationId, $deletedBy);
                $trashedIds[] = $processId;
            }

            return [
                'trashed_count' => count($trashedIds),
                'trashed_ids' => $trashedIds,
                'skipped' => $skipped,
            ];
        });
    }

    private function trashLink(OrganizationProcess $link, string $organizationId, ?string $deletedBy): void
    {
        $previousStatus = OrganizationProcessStatus::fromPivot($link);
        $previousIsActive = $link->is_active;
        $occurredAt = now();

        $link->fill([
            'is_active' => false,
            'status' => OrganizationProcessStatus::INACTIVE,
            'deleted_by' => $deletedBy,
        ]);
        $link->save();
        $link->delete();

        $process = $link->process;
        if ($process === null) {
            return;
        }

        $this->timelineRecorder->handle($process, new RecordProcessTimelineEventData(
            eventType: ProcessTimelineEventType::TRACKING_TRASHED,
            source: ProcessTimelineEventSource::USER,
            idempotencyKey: "tracking-trashed:{$organizationId}:{$process->id}:{$occurredAt->format('U.u')}",
            payload: [
                'from' => ['status' => $previousStatus->value, 'is_active' => $previousIsActive],
                'to' => ['status' => OrganizationProcessStatus::INACTIVE->value, 'is_active' => false],
                'reason' => 'moved_to_trash',
            ],
            organizationId: $organizationId,
            subjectType: 'process',
            subjectId: $process->id,
            actorType: 'admin',
            actorId: $deletedBy,
            occurredAt: $occurredAt,
        ));
    }
}

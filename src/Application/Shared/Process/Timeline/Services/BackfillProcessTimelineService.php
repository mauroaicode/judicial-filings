<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Timeline\Services;

use Src\Application\Shared\Process\Timeline\Contracts\ProcessTimelineRecorder;
use Src\Application\Shared\Process\Timeline\DTOs\RecordProcessTimelineEventData;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessTimelineEvent;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Models\Task;

class BackfillProcessTimelineService
{
    public function __construct(
        private readonly ProcessTimelineRecorder $timelineRecorder,
    ) {}

    /**
     * @return array{created: int, existing: int}
     */
    public function handle(bool $dryRun = false, int $chunkSize = 500): array
    {
        $counts = ['created' => 0, 'existing' => 0];
        $chunkSize = max(1, $chunkSize);

        $this->backfillProcesses($counts, $dryRun, $chunkSize);
        $this->backfillTasks($counts, $dryRun, $chunkSize);
        $this->backfillOrganizationProcesses($counts, $dryRun, $chunkSize);

        return $counts;
    }

    /**
     * @param  array{created: int, existing: int}  $counts
     */
    private function backfillProcesses(array &$counts, bool $dryRun, int $chunkSize): void
    {
        Process::query()
            ->with('processDataSource')
            ->whereNotNull('became_private_at')
            ->chunkById($chunkSize, function ($processes) use (&$counts, $dryRun): void {
                foreach ($processes as $process) {
                    $occurredAt = $process->became_private_at;

                    $this->persist($process, new RecordProcessTimelineEventData(
                        eventType: ProcessTimelineEventType::PROCESS_BECAME_PRIVATE,
                        source: ProcessTimelineEventSource::BACKFILL,
                        idempotencyKey: "privacy:{$process->id}:{$occurredAt->format('U.u')}",
                        payload: [
                            'from' => ['is_private' => false],
                            'to' => ['is_private' => true],
                            'reason' => 'became_private_at_backfill',
                        ],
                        subjectType: 'process',
                        subjectId: $process->id,
                        actorType: 'system',
                        occurredAt: $occurredAt,
                        isBackfilled: true,
                    ), $counts, $dryRun);

                    if ($process->processDataSource?->slug !== ProcessDataSourceSlug::Samai->value) {
                        continue;
                    }

                    $this->persist($process, new RecordProcessTimelineEventData(
                        eventType: ProcessTimelineEventType::PROCESS_SOURCE_CHANGED,
                        source: ProcessTimelineEventSource::BACKFILL,
                        idempotencyKey: "source:{$process->id}:samai:{$process->samai_corporacion}",
                        payload: [
                            'from' => ['data_source' => ProcessDataSourceSlug::JudicialBranch->value],
                            'to' => [
                                'data_source' => ProcessDataSourceSlug::Samai->value,
                                'samai_corporacion' => $process->samai_corporacion,
                            ],
                            'reason' => 'current_source_backfill',
                        ],
                        subjectType: 'process',
                        subjectId: $process->id,
                        actorType: 'system',
                        occurredAt: $process->updated_at,
                        isBackfilled: true,
                        occurredAtIsEstimated: true,
                    ), $counts, $dryRun);
                }
            });
    }

    /**
     * @param  array{created: int, existing: int}  $counts
     */
    private function backfillTasks(array &$counts, bool $dryRun, int $chunkSize): void
    {
        Task::query()
            ->withTrashed()
            ->with('process')
            ->whereNotNull('process_id')
            ->chunkById($chunkSize, function ($tasks) use (&$counts, $dryRun): void {
                foreach ($tasks as $task) {
                    $process = $task->process;

                    if (! $process instanceof Process) {
                        continue;
                    }

                    $this->persist($process, new RecordProcessTimelineEventData(
                        eventType: ProcessTimelineEventType::TASK_CREATED,
                        source: ProcessTimelineEventSource::BACKFILL,
                        idempotencyKey: "task:{$task->id}:created",
                        payload: [
                            'title' => $task->title,
                            'type' => $task->type->value,
                            'status' => TaskStatus::PENDING->value,
                            'due_date' => $task->due_date?->toISOString(),
                            'reminder_days' => $task->reminder_days,
                        ],
                        organizationId: $task->organization_id,
                        subjectType: 'task',
                        subjectId: $task->id,
                        actorType: 'system',
                        occurredAt: $task->created_at,
                        isBackfilled: true,
                    ), $counts, $dryRun);

                    if ($task->status !== TaskStatus::PENDING) {
                        $this->persist($process, new RecordProcessTimelineEventData(
                            eventType: ProcessTimelineEventType::TASK_STATUS_CHANGED,
                            source: ProcessTimelineEventSource::BACKFILL,
                            idempotencyKey: "backfill:task:{$task->id}:status:{$task->status->value}",
                            payload: [
                                'from' => null,
                                'to' => $task->status->value,
                                'reason' => 'current_status_backfill',
                            ],
                            organizationId: $task->organization_id,
                            subjectType: 'task',
                            subjectId: $task->id,
                            actorType: 'system',
                            occurredAt: $task->updated_at,
                            isBackfilled: true,
                            occurredAtIsEstimated: true,
                        ), $counts, $dryRun);
                    }

                    if ($task->deleted_at !== null) {
                        $this->persist($process, new RecordProcessTimelineEventData(
                            eventType: ProcessTimelineEventType::TASK_DELETED,
                            source: ProcessTimelineEventSource::BACKFILL,
                            idempotencyKey: "task:{$task->id}:deleted:".$task->deleted_at->format('U.u'),
                            payload: ['task_type' => $task->type->value],
                            organizationId: $task->organization_id,
                            subjectType: 'task',
                            subjectId: $task->id,
                            actorType: 'system',
                            occurredAt: $task->deleted_at,
                            isBackfilled: true,
                        ), $counts, $dryRun);
                    }
                }
            });
    }

    /**
     * @param  array{created: int, existing: int}  $counts
     */
    private function backfillOrganizationProcesses(array &$counts, bool $dryRun, int $chunkSize): void
    {
        OrganizationProcess::query()
            ->with('process')
            ->orderBy('organization_id')
            ->orderBy('process_id')
            ->chunk($chunkSize, function ($organizationProcesses) use (&$counts, $dryRun): void {
                foreach ($organizationProcesses as $organizationProcess) {
                    $process = $organizationProcess->process;
                    $status = OrganizationProcessStatus::fromPivot($organizationProcess);

                    if ($status !== OrganizationProcessStatus::ACTIVE) {
                        $eventType = $status === OrganizationProcessStatus::SUSPENDED
                            ? ProcessTimelineEventType::PROCESS_SUSPENDED
                            : ProcessTimelineEventType::TRACKING_DEACTIVATED;

                        $this->persist($process, new RecordProcessTimelineEventData(
                            eventType: $eventType,
                            source: ProcessTimelineEventSource::BACKFILL,
                            idempotencyKey: "backfill:organization-process:{$organizationProcess->organization_id}:{$process->id}:{$status->value}",
                            payload: [
                                'from' => null,
                                'to' => ['status' => $status->value, 'is_active' => $organizationProcess->is_active],
                                'reason' => 'current_state_backfill',
                            ],
                            organizationId: $organizationProcess->organization_id,
                            subjectType: 'process',
                            subjectId: $process->id,
                            actorType: 'system',
                            occurredAt: $organizationProcess->updated_at ?? $organizationProcess->created_at,
                            isBackfilled: true,
                            occurredAtIsEstimated: true,
                        ), $counts, $dryRun);
                    }

                    if ($organizationProcess->inactivity_alert_level === null) {
                        continue;
                    }

                    $this->persist($process, new RecordProcessTimelineEventData(
                        eventType: ProcessTimelineEventType::SEMAPHORE_CHANGED,
                        source: ProcessTimelineEventSource::BACKFILL,
                        idempotencyKey: "backfill:semaphore:{$organizationProcess->organization_id}:{$process->id}:{$organizationProcess->inactivity_alert_level}",
                        payload: [
                            'from' => null,
                            'to' => $organizationProcess->inactivity_alert_level,
                            'lawyer_role' => $organizationProcess->lawyer_role?->value,
                            'reason' => 'current_state_backfill',
                        ],
                        organizationId: $organizationProcess->organization_id,
                        subjectType: 'organization_process',
                        actorType: 'system',
                        occurredAt: $organizationProcess->updated_at ?? $organizationProcess->created_at,
                        isBackfilled: true,
                        occurredAtIsEstimated: true,
                    ), $counts, $dryRun);
                }
            });
    }

    /**
     * @param  array{created: int, existing: int}  $counts
     */
    private function persist(
        Process $process,
        RecordProcessTimelineEventData $data,
        array &$counts,
        bool $dryRun,
    ): void {
        if (ProcessTimelineEvent::query()->where('idempotency_key', $data->idempotencyKey)->exists()) {
            $counts['existing']++;

            return;
        }

        $counts['created']++;

        if (! $dryRun) {
            $this->timelineRecorder->handle($process, $data);
        }
    }
}

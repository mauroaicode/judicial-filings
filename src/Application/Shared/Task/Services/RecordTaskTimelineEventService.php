<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Services;

use Src\Application\Shared\Process\Timeline\Contracts\ProcessTimelineRecorder;
use Src\Application\Shared\Process\Timeline\DTOs\RecordProcessTimelineEventData;
use Src\Application\Shared\Task\Support\TaskTimelineState;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Task\Models\Task;

class RecordTaskTimelineEventService
{
    public function __construct(
        private readonly ProcessTimelineRecorder $timelineRecorder,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(Task $task, ProcessTimelineEventType $eventType, array $context = []): void
    {
        match ($eventType) {
            ProcessTimelineEventType::TASK_CREATED => $this->recordCreated($task),
            ProcessTimelineEventType::TASK_UPDATED => $this->recordUpdated($task, $context['before'] ?? []),
            ProcessTimelineEventType::TASK_STATUS_CHANGED => $this->recordStatusChanged(
                $task,
                $context['from'] ?? null,
                $context['to'] ?? null,
            ),
            ProcessTimelineEventType::TASK_DELETED,
            ProcessTimelineEventType::TASK_RESTORED => $this->recordLifecycleEvent($task, $eventType),
            default => null,
        };
    }

    private function recordCreated(Task $task): void
    {
        $process = $task->process;

        if ($process === null) {
            return;
        }

        $this->timelineRecorder->handle($process, new RecordProcessTimelineEventData(
            eventType: ProcessTimelineEventType::TASK_CREATED,
            source: ProcessTimelineEventSource::USER,
            idempotencyKey: "task:{$task->id}:created",
            payload: [
                'title' => $task->title,
                'type' => TaskTimelineState::normalize($task->type),
                'status' => TaskTimelineState::normalize($task->status),
                'due_date' => TaskTimelineState::normalize($task->due_date),
                'reminder_days' => $task->reminder_days,
            ],
            organizationId: $task->organization_id,
            subjectType: 'task',
            subjectId: $task->id,
            occurredAt: $task->created_at,
        ));
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function recordUpdated(Task $task, array $before): void
    {
        $process = $task->process;
        $after = TaskTimelineState::capture($task);
        $changes = [];

        foreach ($after as $attribute => $value) {
            $previous = $before[$attribute] ?? null;

            if ($previous !== $value) {
                $changes[$attribute] = ['from' => $previous, 'to' => $value];
            }
        }

        if ($process === null || $changes === []) {
            return;
        }

        $this->timelineRecorder->handle($process, new RecordProcessTimelineEventData(
            eventType: ProcessTimelineEventType::TASK_UPDATED,
            source: ProcessTimelineEventSource::USER,
            idempotencyKey: "task:{$task->id}:updated:".$task->updated_at?->format('U.u'),
            payload: ['changes' => $changes],
            organizationId: $task->organization_id,
            subjectType: 'task',
            subjectId: $task->id,
            occurredAt: $task->updated_at,
        ));
    }

    private function recordStatusChanged(Task $task, mixed $from, mixed $to): void
    {
        $process = $task->process;
        $from = TaskTimelineState::normalize($from);
        $to = TaskTimelineState::normalize($to);

        if ($process === null || $from === $to) {
            return;
        }

        $this->timelineRecorder->handle($process, new RecordProcessTimelineEventData(
            eventType: ProcessTimelineEventType::TASK_STATUS_CHANGED,
            source: ProcessTimelineEventSource::USER,
            idempotencyKey: "task:{$task->id}:status:{$to}:".$task->updated_at?->format('U.u'),
            payload: [
                'from' => $from,
                'to' => $to,
                'task_type' => TaskTimelineState::normalize($task->type),
            ],
            organizationId: $task->organization_id,
            subjectType: 'task',
            subjectId: $task->id,
            occurredAt: $task->updated_at,
        ));
    }

    private function recordLifecycleEvent(Task $task, ProcessTimelineEventType $eventType): void
    {
        $process = $task->process;
        $operation = $eventType === ProcessTimelineEventType::TASK_DELETED ? 'deleted' : 'restored';
        $occurredAt = $operation === 'deleted' ? $task->deleted_at : $task->updated_at;

        if ($process === null) {
            return;
        }

        $this->timelineRecorder->handle($process, new RecordProcessTimelineEventData(
            eventType: $eventType,
            source: ProcessTimelineEventSource::USER,
            idempotencyKey: "task:{$task->id}:{$operation}:".$occurredAt?->format('U.u'),
            payload: ['task_type' => TaskTimelineState::normalize($task->type)],
            organizationId: $task->organization_id,
            subjectType: 'task',
            subjectId: $task->id,
            occurredAt: $occurredAt,
        ));
    }
}

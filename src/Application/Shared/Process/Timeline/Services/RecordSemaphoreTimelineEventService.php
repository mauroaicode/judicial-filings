<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Timeline\Services;

use Carbon\CarbonInterface;
use Src\Application\Shared\Process\Timeline\Contracts\ProcessTimelineRecorder;
use Src\Application\Shared\Process\Timeline\DTOs\RecordProcessTimelineEventData;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

class RecordSemaphoreTimelineEventService
{
    public function __construct(
        private readonly ProcessTimelineRecorder $timelineRecorder,
    ) {}

    public function handle(
        Process $process,
        string $organizationId,
        ?string $from,
        ?string $to,
        string $reason,
        ?string $lawyerRole = null,
        ProcessTimelineEventSource $source = ProcessTimelineEventSource::SYSTEM,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?CarbonInterface $occurredAt = null,
        ?ProcessAction $action = null,
    ): void {
        if ($from === $to) {
            return;
        }

        $occurredAt ??= now();
        $identity = $subjectId ?? $occurredAt->format('U.u');

        if (! $action instanceof \Src\Domain\Process\Models\ProcessAction && $subjectType === 'process_action' && is_string($subjectId) && $subjectId !== '') {
            $action = ProcessAction::query()->find($subjectId);
        }

        $payload = [
            'from' => $from,
            'to' => $to,
            'lawyer_role' => $lawyerRole,
            'last_activity_date' => $process->last_activity_date?->toDateString(),
            'reason' => $reason,
            'dates' => $this->buildDatesPayload($reason, $process, $action, $occurredAt),
        ];

        if ($reason === 'new_judicial_action') {
            $payload['stored_level_after_reset'] = null;
        }

        $this->timelineRecorder->handle($process, new RecordProcessTimelineEventData(
            eventType: ProcessTimelineEventType::SEMAPHORE_CHANGED,
            source: $source,
            idempotencyKey: "semaphore:{$organizationId}:{$process->id}:{$from}:{$to}:{$identity}",
            payload: $payload,
            organizationId: $organizationId,
            subjectType: $subjectType,
            subjectId: $subjectId,
            actorType: 'job',
            occurredAt: $occurredAt,
        ));
    }

    /**
     * Fechas tipadas para UI. `attribute` es el nombre técnico del campo de origen.
     *
     * @return list<array{key: string, attribute: string, value: string}>
     */
    private function buildDatesPayload(
        string $reason,
        Process $process,
        ?ProcessAction $action,
        CarbonInterface $occurredAt,
    ): array {
        $dates = [
            [
                'key' => 'semaphore_recorded_at',
                'attribute' => 'occurred_at',
                'value' => $occurredAt->toDateTimeString(),
            ],
        ];

        if ($reason === 'new_judicial_action' && $action instanceof ProcessAction) {
            $dates[] = [
                'key' => 'action_date',
                'attribute' => 'action_date',
                'value' => $action->action_date->toDateString(),
            ];

            $dates[] = [
                'key' => 'registration_date',
                'attribute' => 'registration_date',
                'value' => $action->registration_date->toDateString(),
            ];

            return $dates;
        }

        if ($process->last_activity_date !== null) {
            $dates[] = [
                'key' => 'last_activity_date',
                'attribute' => 'last_activity_date',
                'value' => $process->last_activity_date->toDateString(),
            ];
        }

        return $dates;
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Timeline\Services;

use Carbon\CarbonInterface;
use Src\Application\Shared\Process\Timeline\Contracts\ProcessTimelineRecorder;
use Src\Application\Shared\Process\Timeline\DTOs\RecordProcessTimelineEventData;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;

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
    ): void {
        if ($from === $to) {
            return;
        }

        $occurredAt ??= now();
        $identity = $subjectId ?? $occurredAt->format('U.u');
        $payload = [
            'from' => $from,
            'to' => $to,
            'lawyer_role' => $lawyerRole,
            'last_activity_date' => $process->last_activity_date?->toDateString(),
            'reason' => $reason,
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
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Timeline\Services;

use Carbon\CarbonInterface;
use Src\Application\Shared\Process\Timeline\Contracts\ProcessTimelineRecorder;
use Src\Application\Shared\Process\Timeline\DTOs\RecordProcessTimelineEventData;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;

class RecordSpeakerChangedTimelineEventService
{
    public function __construct(
        private readonly ProcessTimelineRecorder $timelineRecorder,
    ) {}

    /**
     * Record a timeline event when the reporting judge (ponente) changes.
     * Skips first fill (empty → name) and clear-without-replacement.
     */
    public function handle(
        Process $process,
        ?string $from,
        ?string $to,
        ProcessTimelineEventSource $source = ProcessTimelineEventSource::JUDICIAL_BRANCH,
        ?CarbonInterface $occurredAt = null,
        string $reason = 'speaker_updated_from_sync',
    ): void {
        $fromNormalized = $this->normalize($from);
        $toNormalized = $this->normalize($to);

        if ($fromNormalized === $toNormalized) {
            return;
        }

        if ($fromNormalized === '' || $toNormalized === '') {
            return;
        }

        $occurredAt ??= now();

        $this->timelineRecorder->handle($process, new RecordProcessTimelineEventData(
            eventType: ProcessTimelineEventType::SPEAKER_CHANGED,
            source: $source,
            idempotencyKey: "speaker:{$process->id}:{$fromNormalized}:{$toNormalized}:{$occurredAt->format('Y-m-d')}",
            payload: [
                'from' => $fromNormalized,
                'to' => $toNormalized,
                'reason' => $reason,
                'dates' => [
                    [
                        'key' => 'speaker_changed_at',
                        'attribute' => 'occurred_at',
                        'value' => $occurredAt->toDateTimeString(),
                    ],
                ],
            ],
            subjectType: 'process',
            subjectId: $process->id,
            actorType: 'job',
            occurredAt: $occurredAt,
        ));
    }

    private function normalize(?string $value): string
    {
        return trim((string) $value);
    }
}

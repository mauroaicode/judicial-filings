<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Services;

use Illuminate\Support\Facades\Date;
use Src\Application\Shared\Process\Support\SpeakerChangeAnnotationParser;
use Src\Application\Shared\Process\Timeline\Services\RecordSpeakerChangedTimelineEventService;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

class ApplySpeakerChangeFromProcessActionService
{
    public function __construct(
        private readonly RecordSpeakerChangedTimelineEventService $recordSpeakerChangedTimelineEventService,
    ) {}

    /**
     * Update process.speaker from a "Cambio de ponente" action and record timeline when A → B.
     */
    public function handle(
        Process $process,
        ProcessAction $action,
        ProcessTimelineEventSource $source = ProcessTimelineEventSource::JUDICIAL_BRANCH,
    ): bool {
        $parsed = SpeakerChangeAnnotationParser::parse($action->action, $action->annotation);
        if ($parsed === null || $parsed['new'] === null) {
            return false;
        }

        $newSpeaker = $parsed['new'];
        $currentSpeaker = trim((string) ($process->speaker ?? ''));

        if ($this->sameSpeaker($currentSpeaker, $newSpeaker)) {
            return false;
        }

        $fromSpeaker = $currentSpeaker !== ''
            ? $currentSpeaker
            : $parsed['previous'];

        Process::query()
            ->whereKey($process->id)
            ->update([
                'speaker' => $newSpeaker,
                'updated_at' => now(),
            ]);

        $process->refresh();

        $occurredAt = $action->action_date
            ?? $action->registration_date
            ?? $action->created_at
            ?? Date::now();

        $this->recordSpeakerChangedTimelineEventService->handle(
            process: $process,
            from: $fromSpeaker,
            to: $newSpeaker,
            source: $source,
            occurredAt: $occurredAt,
            reason: 'speaker_updated_from_action',
            subjectType: 'process_action',
            subjectId: $action->id,
        );

        return true;
    }

    private function sameSpeaker(string $left, string $right): bool
    {
        return mb_strtolower(trim($left)) === mb_strtolower(trim($right));
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Timeline\Services;

use Illuminate\Database\QueryException;
use Src\Application\Shared\Process\Timeline\Contracts\ProcessTimelineRecorder;
use Src\Application\Shared\Process\Timeline\DTOs\RecordProcessTimelineEventData;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessTimelineEvent;

class RecordProcessTimelineEventService implements ProcessTimelineRecorder
{
    public function __construct(
        private readonly ResolveTimelineActorService $resolveTimelineActorService,
    ) {}

    public function handle(Process $process, RecordProcessTimelineEventData $data): ProcessTimelineEvent
    {
        [$actorType, $actorId] = $this->resolveActor($data);

        try {
            return ProcessTimelineEvent::query()->firstOrCreate(
                ['idempotency_key' => $data->idempotencyKey],
                [
                    'process_id' => $process->id,
                    'process_number' => $process->process_number,
                    'organization_id' => $data->organizationId,
                    'event_type' => $data->eventType,
                    'occurred_at' => $data->occurredAt ?? now(),
                    'recorded_at' => now(),
                    'source' => $data->source,
                    'subject_type' => $data->subjectType,
                    'subject_id' => $data->subjectId,
                    'actor_type' => $actorType,
                    'actor_id' => $actorId,
                    'payload' => $data->payload,
                    'is_backfilled' => $data->isBackfilled,
                    'occurred_at_is_estimated' => $data->occurredAtIsEstimated,
                ],
            );
        } catch (QueryException $exception) {
            $existing = ProcessTimelineEvent::query()
                ->where('idempotency_key', $data->idempotencyKey)
                ->first();

            if ($existing instanceof ProcessTimelineEvent) {
                return $existing;
            }

            throw $exception;
        }
    }

    /**
     * @return array{string|null, string|null}
     */
    private function resolveActor(RecordProcessTimelineEventData $data): array
    {
        if ($data->actorType !== null || $data->actorId !== null) {
            return [$data->actorType, $data->actorId];
        }

        if ($data->source !== ProcessTimelineEventSource::USER) {
            return [null, null];
        }

        $actor = $this->resolveTimelineActorService->handle();

        return [$actor['type'], $actor['id']];
    }
}

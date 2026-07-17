<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Timeline\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Process\Timeline\Presenters\ProcessTimelineEventPresenter;
use Src\Domain\Process\Models\ProcessTimelineEvent;

class ProcessTimelineEventResource extends Resource
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $display
     */
    public function __construct(
        public string $id,
        public string $event_type,
        public string $source,
        public string $process_id,
        public string $process_number,
        public ?string $organization_id,
        public ?string $subject_type,
        public ?string $subject_id,
        public ?string $actor_type,
        public ?string $actor_id,
        public array $payload,
        public array $display,
        public string $occurred_at,
        public string $recorded_at,
        public bool $is_backfilled,
        public bool $occurred_at_is_estimated,
    ) {}

    public static function fromModel(ProcessTimelineEvent $event): self
    {
        return new self(
            id: $event->id,
            event_type: $event->event_type->value,
            source: $event->source->value,
            process_id: $event->process_id,
            process_number: $event->process_number,
            organization_id: $event->organization_id,
            subject_type: $event->subject_type,
            subject_id: $event->subject_id,
            actor_type: $event->actor_type,
            actor_id: $event->actor_id,
            payload: $event->payload,
            display: ProcessTimelineEventPresenter::for($event),
            occurred_at: $event->occurred_at->toISOString(),
            recorded_at: $event->recorded_at->toISOString(),
            is_backfilled: $event->is_backfilled,
            occurred_at_is_estimated: $event->occurred_at_is_estimated,
        );
    }
}

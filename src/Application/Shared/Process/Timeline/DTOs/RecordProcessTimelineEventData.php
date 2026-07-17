<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Timeline\DTOs;

use Carbon\CarbonInterface;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;

readonly class RecordProcessTimelineEventData
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public ProcessTimelineEventType $eventType,
        public ProcessTimelineEventSource $source,
        public string $idempotencyKey,
        public array $payload = [],
        public ?string $organizationId = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public ?string $actorType = null,
        public ?string $actorId = null,
        public ?CarbonInterface $occurredAt = null,
        public bool $isBackfilled = false,
        public bool $occurredAtIsEstimated = false,
    ) {}
}

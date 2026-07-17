<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessTimelineEvent;

/**
 * @extends Factory<ProcessTimelineEvent>
 */
class ProcessTimelineEventFactory extends Factory
{
    protected $model = ProcessTimelineEvent::class;

    public function definition(): array
    {
        return [
            'process_id' => Process::factory(),
            'process_number' => $this->faker->unique()->numerify('#######################'),
            'organization_id' => null,
            'event_type' => ProcessTimelineEventType::PROCESS_BECAME_PRIVATE,
            'occurred_at' => now(),
            'recorded_at' => now(),
            'source' => ProcessTimelineEventSource::SYSTEM,
            'subject_type' => 'process',
            'subject_id' => null,
            'actor_type' => 'system',
            'actor_id' => null,
            'payload' => [],
            'idempotency_key' => (string) Str::uuid(),
            'is_backfilled' => false,
            'occurred_at_is_estimated' => false,
        ];
    }
}

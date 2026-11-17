<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Domain\CourtDirectory\Enums\CourtDirectorySyncStatus;
use Src\Domain\CourtDirectory\Enums\CourtDirectorySyncTrigger;
use Src\Domain\CourtDirectory\Models\CourtDirectorySyncRun;

/**
 * @extends Factory<CourtDirectorySyncRun>
 */
class CourtDirectorySyncRunFactory extends Factory
{
    protected $model = CourtDirectorySyncRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'trigger' => CourtDirectorySyncTrigger::Command,
            'status' => CourtDirectorySyncStatus::Succeeded,
            'rows_fetched' => 1,
            'rows_upserted' => 1,
            'rows_marked_inactive' => 0,
            'error' => null,
        ];
    }
}

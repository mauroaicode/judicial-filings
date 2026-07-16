<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;

/**
 * @extends Factory<JudicialSyncRun>
 */
class JudicialSyncRunFactory extends Factory
{
    protected $model = JudicialSyncRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'started_at' => now()->subMinutes(5),
            'command_finished_at' => now()->subMinutes(5),
            'batch_finished_at' => now()->subMinute(),
            'radicado_filter' => null,
            'data_source' => JudicialSyncDataSource::JudicialBranch,
            'processes_queued' => 3,
            'laravel_batch_id' => null,
            'status' => JudicialSyncRunStatus::BatchCompleted,
            'command_exit_code' => 0,
            'dispatch_error' => null,
            'failed_jobs_count' => 0,
        ];
    }
}

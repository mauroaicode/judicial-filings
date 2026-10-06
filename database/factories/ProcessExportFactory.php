<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessExportStatus;
use Src\Domain\Process\Models\ProcessExport;

/**
 * @extends Factory<ProcessExport>
 */
class ProcessExportFactory extends Factory
{
    protected $model = ProcessExport::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'requested_by' => null,
            'status' => ProcessExportStatus::Pending,
            'filters' => [],
            'row_count' => 0,
            'action_row_count' => 0,
            'disk' => null,
            'file_path' => null,
            'file_name' => null,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
            'expires_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ProcessExportStatus::Completed,
            'row_count' => 1,
            'action_row_count' => 0,
            'disk' => 'local',
            'file_path' => 'exports/processes/example.xlsx',
            'file_name' => 'procesos.xlsx',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'expires_at' => now()->addDay(),
        ]);
    }
}

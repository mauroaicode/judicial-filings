<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessImportBatch;

/**
 * @extends Factory<ProcessImportBatch>
 */
class ProcessImportBatchFactory extends Factory
{
    protected $model = ProcessImportBatch::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement([
            ProcessImportBatch::STATUS_PROCESSING,
            ProcessImportBatch::STATUS_COMPLETED,
            ProcessImportBatch::STATUS_FAILED,
        ]);

        return [
            'id' => Str::uuid(),
            'organization_id' => Organization::factory(),
            'requested_by' => null,
            'file_name' => $this->faker->word().'.xlsx',
            'is_private_import' => false,
            'excel_total_count' => $this->faker->numberBetween(1, 50),
            'total_count' => $this->faker->numberBetween(1, 50),
            'enqueued_process_numbers' => [],
            'success_count' => $this->faker->numberBetween(0, 10),
            'failed_count' => $this->faker->numberBetween(0, 5),
            'multiple_instances_count' => $this->faker->numberBetween(0, 3),
            'status' => $status,
            'errors' => null,
            'laravel_batch_id' => Str::uuid(),
            'completed_at' => $status === ProcessImportBatch::STATUS_COMPLETED ? now() : null,
        ];
    }

    /**
     * Set batch status to processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcessImportBatch::STATUS_PROCESSING,
            'completed_at' => null,
        ]);
    }

    /**
     * Set batch status to completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcessImportBatch::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Set batch status to failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcessImportBatch::STATUS_FAILED,
            'completed_at' => null,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Enums\TaskType;
use Src\Domain\Task\Models\Task;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'type' => TaskType::GENERAL,
            'due_date' => now()->addDays(7)->startOfDay(),
            'reminder_days' => $this->faker->numberBetween(0, 7),
            'status' => TaskStatus::PENDING,
            'is_admin' => false,
            'process_id' => Process::factory(),
            'organization_id' => Organization::factory(),
        ];
    }

    public function admin(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::COMPLETED,
        ]);
    }

    public function draft(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::DRAFT,
        ]);
    }

    public function suspension(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => TaskType::SUSPENSION,
        ]);
    }
}

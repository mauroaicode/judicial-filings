<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

/**
 * @extends Factory<ProcessAction>
 */
class ProcessActionFactory extends Factory
{
    protected $model = ProcessAction::class;

    public function definition(): array
    {
        $actionDate = $this->faker->dateTimeBetween('-1 year', 'now');

        return [
            'id' => Str::uuid(),
            'process_id' => Process::factory(),
            'action_registration_id' => $this->faker->unique()->numberBetween(1000000000, 9999999999),
            'action_date' => $actionDate,
            'action' => $this->faker->randomElement([
                'Recepción memorial oa al despacho',
                'A despacho',
                'RECIBE MEMORIALES ONLINE AL DESPACHO',
                'Citación a audiencia',
                'Sentencia',
                'Auto de trámite',
                'Recurso de apelación',
                'Medida cautelar',
                'Pruebas',
                'Allegados',
            ]),
            'annotation' => $this->faker->optional(0.8)->paragraph(),
            'start_date' => $this->faker->optional(0.3)->dateTimeBetween('-1 year', $actionDate),
            'end_date' => $this->faker->optional(0.2)->dateTimeBetween($actionDate, 'now'),
            'registration_date' => $actionDate,
        ];
    }

    /**
     * Indicate that the action has a detailed annotation.
     */
    public function withDetailedAnnotation(): static
    {
        return $this->state(fn (array $attributes) => [
            'annotation' => $this->faker->paragraph(3),
        ]);
    }

    /**
     * Indicate that the action has a date range.
     */
    public function withDateRange(): static
    {
        $startDate = $this->faker->dateTimeBetween('-1 year', 'now');
        $endDate = $this->faker->dateTimeBetween($startDate, 'now');

        return $this->state(fn (array $attributes) => [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }
}

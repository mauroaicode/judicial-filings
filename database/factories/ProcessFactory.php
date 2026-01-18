<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Src\Domain\Process\Models\Process;

/**
 * @extends Factory<Process>
 */
class ProcessFactory extends Factory
{
    protected $model = Process::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'process_id' => $this->faker->unique()->numberBetween(1000000000, 9999999999),
            'process_number' => $this->faker->unique()->numerify('#######################'), // 23 dígitos
            'court' => $this->faker->randomElement([
                'JUZGADO 017 ADMINISTRATIVO DE CALI',
                'JUZGADO 018 CIVIL DEL CIRCUITO',
                'JUZGADO 019 LABORAL',
                'JUZGADO 020 PENAL',
                'TRIBUNAL ADMINISTRATIVO',
            ]),
            'department' => $this->faker->randomElement([
                'VALLE DEL CAUCA',
                'ANTIOQUIA',
                'CUNDINAMARCA',
                'ATLÁNTICO',
                'BOLÍVAR',
            ]),
            'process_type' => $this->faker->randomElement([
                'ORDINARIO',
                'EJECUTIVO',
                'VERBAL',
                'ADMINISTRATIVO',
                'CONSTITUCIONAL',
            ]),
            'process_class' => $this->faker->randomElement([
                'CIVIL',
                'LABORAL',
                'PENAL',
                'ADMINISTRATIVO',
                'COMERCIAL',
            ]),
            'subclass_process' => $this->faker->optional(0.7)->randomElement([
                'CONTRATO',
                'RESPONSABILIDAD',
                'PROPIEDAD',
                'FAMILIA',
                'SUCESIONES',
            ]),
            'litigants' => $this->faker->optional(0.8)->paragraph(),
            'process_date' => $this->faker->dateTimeBetween('-2 years', '-6 months'),
            'last_activity_date' => $this->faker->optional(0.9)->dateTimeBetween('-6 months', 'now'),
            'location' => $this->faker->optional(0.6)->city(),
            'filing_content' => $this->faker->optional(0.7)->paragraphs(3, true),
            'is_private' => $this->faker->boolean(20), // 20% probabilidad de ser privado
            'has_multiple_instances' => $this->faker->boolean(15), // 15% probabilidad de tener múltiples instancias
            'last_api_update' => $this->faker->optional(0.8)->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the process is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => true,
        ]);
    }

    /**
     * Indicate that the process is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => false,
        ]);
    }
}

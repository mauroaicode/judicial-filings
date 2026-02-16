<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;

/**
 * @extends Factory<ProcessSubject>
 */
class ProcessSubjectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ProcessSubject>
     */
    protected $model = ProcessSubject::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subjectTypes = ['Demandante', 'Demandado', 'Tercero', 'Apoderado', 'Testigo'];

        return [
            'subject_registration_id' => fake()->unique()->numberBetween(10000000, 99999999),
            'subject_type' => fake()->randomElement($subjectTypes),
            'is_cited' => fake()->boolean(20), // 20% chance of being cited
            'identification' => fake()->optional(0.7)->numerify('##########'), // 70% chance of having ID
            'name_or_business_name' => fake()->company().' - '.fake()->companySuffix(),
        ];
    }

    /**
     * After creating, attach the subject to the given process.
     */
    public function forProcess(Process $process): static
    {
        return $this->afterCreating(function (ProcessSubject $subject) use ($process): void {
            $process->subjects()->syncWithoutDetaching([$subject->id]);
        });
    }

    /**
     * Indicate that the subject is a plaintiff (demandante).
     */
    public function plaintiff(): static
    {
        return $this->state(fn (array $attributes) => [
            'subject_type' => 'Demandante',
            'is_cited' => false,
        ]);
    }

    /**
     * Indicate that the subject is a defendant (demandado).
     */
    public function defendant(): static
    {
        return $this->state(fn (array $attributes) => [
            'subject_type' => 'Demandado',
            'is_cited' => false,
        ]);
    }

    /**
     * Indicate that the subject is a natural person.
     */
    public function naturalPerson(): static
    {
        return $this->state(fn (array $attributes) => [
            'name_or_business_name' => fake()->name().' '.fake()->lastName(),
            'identification' => fake()->numerify('##########'),
        ]);
    }

    /**
     * Indicate that the subject is a juridical person (company).
     */
    public function juridicalPerson(): static
    {
        return $this->state(fn (array $attributes) => [
            'name_or_business_name' => fake()->company().' - '.fake()->companySuffix(),
            'identification' => fake()->numerify('##########'),
        ]);
    }
}

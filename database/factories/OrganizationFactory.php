<?php

namespace Database\Factories;

use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Organization::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['natural', 'juridical']);

        return [
            'id' => Str::uuid(),
            'name' => $type === 'natural'
                ? $this->faker->name()
                : $this->faker->company(),
            'slug' => function (array $attributes) {
                return Str::slug($attributes['name']);
            },
            'type' => $type,
            'identification' => $type === 'natural'
                ? $this->faker->numerify('########-#')
                : $this->faker->numerify('##.###.###-#'),
            'address' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'contact_person' => $type === 'juridical' ? $this->faker->name() : null,
        ];
    }

    /**
     * Create a natural person organization
     */
    public function natural(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'natural',
            'name' => $this->faker->name(),
            'identification' => $this->faker->numerify('########-#'),
            'contact_person' => null,
        ]);
    }

    /**
     * Create a juridical person organization
     */
    public function juridical(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'juridical',
            'name' => $this->faker->company(),
            'identification' => $this->faker->numerify('##.###.###-#'),
            'contact_person' => $this->faker->name(),
        ]);
    }
}

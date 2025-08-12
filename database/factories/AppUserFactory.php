<?php

namespace Database\Factories;

use Core\Shared\Infrastructure\Persistence\Eloquent\Models\AppUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AppUser>
 */
class AppUserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AppUser::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();
        $fullName = $firstName . ' ' . $lastName;

        return [
            'id' => Str::uuid(),
            'name' => $firstName,
            'last_name' => $lastName,
            'slug' => Str::slug($fullName),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password1234'),
            'profile_image' => null,
            'email_verified_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create a customer with specific data
     */
    public function withProfileImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'profile_image' => $this->faker->imageUrl(640, 480, 'people'),
        ]);
    }
}

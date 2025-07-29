<?php

namespace Database\Factories;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;
use Core\BoundedContext\Admin\User\Infrastructure\Persistence\Eloquent\UserModel;

/**
 * @extends Factory<UserModel>
 */
class UserFactory extends Factory
{
    protected $model = UserModel::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->name();
        $lastName = $this->faker->lastName;

        return [
            'id' => $this->faker->uuid,
            'name' => $name,
            'last_name' => $lastName,
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'slug' => Str::slug(strtolower($name) . '-' . strtolower($lastName) . Str::random(5), '-'),
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
}

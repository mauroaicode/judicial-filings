<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Organization\Models\Organization;

/**
 * @extends Factory<NotificationDigest>
 */
class NotificationDigestFactory extends Factory
{
    protected $model = NotificationDigest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->uuid(),
            'organization_id' => Organization::factory(),
            'data' => [
                [
                    'court' => fake()->company(),
                    'process_number' => fake()->numerify('#######################'),
                    'action_date' => fake()->date('d/m/Y'),
                    'registration_date' => fake()->date('d/m/Y'),
                    'action_text' => fake()->sentence(),
                    'annotation' => fake()->paragraph(),
                ],
            ],
            'email_sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

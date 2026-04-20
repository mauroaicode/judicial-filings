<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Domain\AiChat\Models\AiChat;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;

/**
 * @extends Factory<AiChat>
 */
class AiChatFactory extends Factory
{
    protected $model = AiChat::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->uuid(),
            'organization_id' => Organization::factory(),
            'process_id' => Process::factory(),
            'app_user_id' => AppUser::factory(),
            'title' => $this->faker->sentence(3),
            'is_private' => false,
            'is_active' => true,
        ];
    }
}

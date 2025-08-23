<?php

namespace Database\Factories;

use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotificationChannel;
use Core\Shared\Domain\Enums\NotificationChannelType;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotificationChannel>
 */
class OrganizationNotificationChannelFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = OrganizationNotificationChannel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $channelType = $this->faker->randomElement(NotificationChannelType::cases());

        return [
            'id' => Str::uuid(),
            'organization_id' => Organization::factory(),
            'channel_type' => $channelType,
            'channel_value' => $this->getChannelValue($channelType),
            'is_active' => $this->faker->boolean(80), // 80% probability of being active
            'priority' => $this->faker->numberBetween(1, 3),
        ];
    }

    /**
     * Get appropriate channel value based on channel type.
     */
    private function getChannelValue(NotificationChannelType $channelType): string
    {
        return match ($channelType) {
            NotificationChannelType::EMAIL => $this->faker->unique()->safeEmail(),
            NotificationChannelType::WHATSAPP, NotificationChannelType::SMS => $this->faker->phoneNumber(),
            NotificationChannelType::INTERNAL => $this->faker->randomElement(['dashboard', 'mobile_app', 'web_app']),
        };
    }

    /**
     * Create an email channel.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel_type' => NotificationChannelType::EMAIL,
            'channel_value' => $this->faker->unique()->safeEmail(),
        ]);
    }

    /**
     * Create a WhatsApp channel.
     */
    public function whatsapp(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel_type' => NotificationChannelType::WHATSAPP,
            'channel_value' => $this->faker->phoneNumber(),
        ]);
    }

    /**
     * Create an SMS channel.
     */
    public function sms(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel_type' => NotificationChannelType::SMS,
            'channel_value' => $this->faker->phoneNumber(),
        ]);
    }

    /**
     * Create an internal channel.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel_type' => NotificationChannelType::INTERNAL,
            'channel_value' => $this->faker->randomElement(['dashboard', 'mobile_app', 'web_app']),
        ]);
    }

    /**
     * Create an active channel.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Create an inactive channel.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}

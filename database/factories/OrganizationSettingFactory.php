<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Organization\Models\OrganizationSetting;

/**
 * @extends Factory<OrganizationSetting>
 */
class OrganizationSettingFactory extends Factory
{
    protected $model = OrganizationSetting::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'organization_id' => Organization::factory(),
            'max_active_processes' => null,
        ];
    }

    public function withMaxActiveProcesses(int $max): static
    {
        return $this->state(fn (array $attributes): array => [
            'max_active_processes' => $max,
        ]);
    }
}

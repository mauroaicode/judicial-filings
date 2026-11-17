<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Domain\CourtDirectory\Models\CourtDirectoryEntry;

/**
 * @extends Factory<CourtDirectoryEntry>
 */
class CourtDirectoryEntryFactory extends Factory
{
    protected $model = CourtDirectoryEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => 'Juzgado 001 Civil Municipal de Cali',
            'corporation' => 'Juzgado Municipal',
            'specialty' => 'Civil',
            'department' => 'Valle',
            'city' => 'Cali',
            'account_type' => 'Área',
            'desk_code' => '760014189001',
            'is_active' => true,
            'last_seen_at' => now(),
            'missing_at' => null,
            'last_sync_run_id' => null,
        ];
    }
}

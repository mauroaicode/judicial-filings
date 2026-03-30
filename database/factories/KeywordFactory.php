<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Domain\Keyword\Enums\KeywordStatus;
use Src\Domain\Keyword\Models\Keyword;
use Src\Domain\Organization\Models\Organization;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\Domain\Keyword\Models\Keyword>
 */
class KeywordFactory extends Factory
{
    /**
     * @var class-string<Keyword>
     */
    protected $model = Keyword::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->words(2, true),
            'keyword' => $this->faker->word(),
            'status' => $this->faker->randomElement(KeywordStatus::cases()),
        ];
    }
}

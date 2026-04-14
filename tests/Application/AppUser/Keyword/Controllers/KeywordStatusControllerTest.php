<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\Keyword\Controllers;

use Src\Domain\AppUser\Models\AppUser;
use Tests\TestCase;

class KeywordStatusControllerTest extends TestCase
{
    public function test_it_returns_all_keyword_statuses(): void
    {
        $user = AppUser::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/app-user/keywords/statuses');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => ['value', 'label'],
        ]);

        $response->assertJsonFragment(['value' => 'active']);
        $response->assertJsonFragment(['value' => 'inactive']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\Organization\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Tests\TestCase;

class GetOrganizationAiStatusControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_returns_ai_status_for_organization(): void
    {
        $organization = Organization::factory()->create(['is_ai_enabled' => true]);
        $appUser = AppUser::factory()->create();
        $appUser->organizations()->attach($organization->id);

        $response = $this->actingAs($appUser, 'sanctum')
            ->getJson("/api/app-user/organizations/{$organization->id}/ai-status");

        $response->assertStatus(200)
            ->assertJson(['is_ai_enabled' => true]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\Organization\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Domain\AppUser\Models\AppUser;
use Tests\TestCase;

class UpdateOrganizationAiAccessControllerTest extends TestCase
{
    private AppUser $appUser;

    private string $organizationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appUser = AppUser::factory()->create();

        $this->organizationId = (string) Str::uuid();

        DB::table('organizations')->insert([
            'id' => $this->organizationId,
            'name' => 'Test Organization',
            'slug' => 'test-organization-'.Str::random(5),
            'type' => 'juridical',
            'is_active' => true,
            'is_ai_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_it_can_enable_ai_access_for_organization(): void
    {
        $response = $this->actingAs($this->appUser, 'sanctum')->putJson(
            "/api/app-user/organizations/{$this->organizationId}/ai-access",
            ['is_ai_enabled' => true]
        );

        $response->assertOk()
            ->assertJson([
                'message' => 'El acceso a IA de la organización ha sido actualizado exitosamente.',
                'is_ai_enabled' => true,
            ]);

        $this->assertDatabaseHas('organizations', [
            'id' => $this->organizationId,
            'is_ai_enabled' => true,
        ]);
    }

    public function test_it_can_disable_ai_access_for_organization(): void
    {
        DB::table('organizations')
            ->where('id', $this->organizationId)
            ->update(['is_ai_enabled' => true]);

        $response = $this->actingAs($this->appUser, 'sanctum')->putJson(
            "/api/app-user/organizations/{$this->organizationId}/ai-access",
            ['is_ai_enabled' => false]
        );

        $response->assertOk()
            ->assertJson([
                'message' => 'El acceso a IA de la organización ha sido actualizado exitosamente.',
                'is_ai_enabled' => false,
            ]);

        $this->assertDatabaseHas('organizations', [
            'id' => $this->organizationId,
            'is_ai_enabled' => false,
        ]);
    }

    public function test_it_validates_is_ai_enabled_is_boolean(): void
    {
        $response = $this->actingAs($this->appUser, 'sanctum')->putJson(
            "/api/app-user/organizations/{$this->organizationId}/ai-access",
            ['is_ai_enabled' => 'not-a-boolean']
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['is_ai_enabled']);
    }
}

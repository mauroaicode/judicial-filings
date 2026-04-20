<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\AiChat\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Tests\TestCase;

class StoreAiChatControllerTest extends TestCase
{
    use DatabaseTransactions;

    private AppUser $appUser;

    private Organization $organization;

    private Process $process;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(['is_ai_enabled' => true]);
        $this->appUser = AppUser::factory()->create();
        $this->appUser->organizations()->attach($this->organization->id);

        $this->process = Process::factory()->create();
        $this->process->organizations()->attach($this->organization->id, [
            'is_active' => true,
            'interest_date' => now()->toDateString(),
        ]);
    }

    public function test_it_can_create_a_new_ai_chat(): void
    {
        $payload = [
            'process_id' => $this->process->id,
            'process_number' => 'Test-123',
            'title' => 'Test Chat',
            'is_private' => false,
        ];

        $response = $this->actingAs($this->appUser, 'sanctum')->postJson('/api/app-user/ai-chats', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'title', 'is_private', 'created_at']);

        $this->assertDatabaseHas('ai_chats', [
            'process_id' => $this->process->id,
            'title' => 'Test Chat',
            'is_private' => false,
            'app_user_id' => $this->appUser->id,
        ]);
    }

    public function test_it_returns_403_if_ai_is_disabled(): void
    {
        $this->organization->update(['is_ai_enabled' => false]);

        $payload = [
            'process_id' => $this->process->id,
            'process_number' => 'Test-123',
        ];

        $response = $this->actingAs($this->appUser, 'sanctum')->postJson('/api/app-user/ai-chats', $payload);

        $response->assertStatus(403);
    }
}

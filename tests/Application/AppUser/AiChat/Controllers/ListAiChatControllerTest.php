<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\AiChat\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Src\Domain\AiChat\Models\AiChat;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Tests\TestCase;

class ListAiChatControllerTest extends TestCase
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

    public function test_it_can_list_chats_for_a_process(): void
    {
        AiChat::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'process_id' => $this->process->id,
            'app_user_id' => $this->appUser->id,
            'is_private' => false,
        ]);

        $response = $this->actingAs($this->appUser, 'sanctum')->getJson("/api/app-user/ai-chats/process/{$this->process->id}");

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_it_does_not_list_private_chats_from_other_users(): void
    {
        $otherUser = AppUser::factory()->create();
        $otherUser->organizations()->attach($this->organization->id);

        // Private chat from other user
        AiChat::factory()->create([
            'organization_id' => $this->organization->id,
            'process_id' => $this->process->id,
            'app_user_id' => $otherUser->id,
            'is_private' => true,
        ]);

        // Public chat from other user
        AiChat::factory()->create([
            'organization_id' => $this->organization->id,
            'process_id' => $this->process->id,
            'app_user_id' => $otherUser->id,
            'is_private' => false,
        ]);

        $response = $this->actingAs($this->appUser, 'sanctum')->getJson("/api/app-user/ai-chats/process/{$this->process->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1); // Only the public one
    }
}

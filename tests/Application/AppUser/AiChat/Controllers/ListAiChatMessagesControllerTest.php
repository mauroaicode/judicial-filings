<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\AiChat\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Src\Domain\AiChat\Models\AiChat;
use Src\Domain\AiChat\Models\AiChatMessage;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Tests\TestCase;

class ListAiChatMessagesControllerTest extends TestCase
{
    use DatabaseTransactions;

    private AppUser $appUser;

    private Organization $organization;

    private AiChat $chat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(['is_ai_enabled' => true]);
        $this->appUser = AppUser::factory()->create();
        $this->appUser->organizations()->attach($this->organization->id);

        $process = Process::factory()->create();
        $process->organizations()->attach($this->organization->id, [
            'is_active' => true,
            'interest_date' => now()->toDateString(),
        ]);

        $this->chat = AiChat::factory()->create([
            'organization_id' => $this->organization->id,
            'process_id' => $process->id,
            'app_user_id' => $this->appUser->id,
        ]);
    }

    public function test_it_can_list_messages_for_a_chat(): void
    {
        AiChatMessage::factory()->count(5)->create([
            'ai_chat_id' => $this->chat->id,
        ]);

        $response = $this->actingAs($this->appUser, 'sanctum')->getJson("/api/app-user/ai-chats/{$this->chat->id}/messages");

        $response->assertStatus(200)
            ->assertJsonCount(5);
    }
}

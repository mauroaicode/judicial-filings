<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\AiChat\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Src\Application\AppUser\AiChat\Jobs\UpdateAiChatTitleJob;
use Src\Domain\AiChat\Models\AiChat;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Tests\TestCase;

class AiChatVoiceControllerTest extends TestCase
{
    use DatabaseTransactions;

    private AppUser $appUser;

    private Organization $organization;

    private Process $process;

    private AiChat $chat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create([
            'is_ai_enabled' => true,
        ]);
        $this->appUser = AppUser::factory()->create();
        $this->appUser->organizations()->attach($this->organization->id);

        $this->process = Process::factory()->public()->create();
        $this->process->organizations()->attach($this->organization->id, [
            'is_active' => true,
            'interest_date' => now()->toDateString(),
        ]);

        $this->chat = AiChat::factory()->create([
            'organization_id' => $this->organization->id,
            'process_id' => $this->process->id,
            'app_user_id' => $this->appUser->id,
        ]);
    }

    public function test_it_saves_user_message_and_returns_sse_stream(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->appUser, 'sanctum')
            ->postJson("/api/app-user/ai-chats/{$this->chat->id}/voice", [
                'content' => '¿Qué dice el proceso?',
            ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('content-type'));

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_id' => $this->chat->id,
            'role' => 'user',
            'content' => '¿Qué dice el proceso?',
            'search_mode' => null,
        ]);

        Queue::assertPushed(UpdateAiChatTitleJob::class);
    }
}

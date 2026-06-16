<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\AiChat\Support;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Src\Application\AppUser\AiChat\Support\RagQueryStreamBodyHelper;
use Src\Domain\AiChat\Models\AiChat;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Tests\TestCase;

class RagQueryStreamBodyHelperTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_builds_body_with_app_user_session_id_and_enable_memory(): void
    {
        config(['ai-chat.enable_memory' => true]);

        $organization = Organization::factory()->create(['is_ai_enabled' => true]);
        $appUser = AppUser::factory()->create();
        $appUser->organizations()->attach($organization->id);
        $process = Process::factory()->public()->create();
        $process->organizations()->attach($organization->id, [
            'is_active' => true,
            'interest_date' => now()->toDateString(),
        ]);
        $chat = AiChat::factory()->create([
            'organization_id' => $organization->id,
            'process_id' => $process->id,
            'app_user_id' => $appUser->id,
        ]);

        $body = RagQueryStreamBodyHelper::build(
            sessionId: (string) $appUser->id,
            query: '¿Quiénes son los demandantes?',
            mode: 'auto',
            source: 'voice',
            responseType: 'paragraph',
            history: [
                ['role' => 'user', 'content' => 'Hola'],
                ['role' => 'assistant', 'content' => 'Buenas tardes'],
            ],
            userPrompt: 'Responde breve.',
        );

        $this->assertSame('¿Quiénes son los demandantes?', $body['query']);
        $this->assertSame('auto', $body['mode']);
        $this->assertSame('voice', $body['source']);
        $this->assertSame((string) $appUser->id, $body['session_id']);
        $this->assertNotSame((string) $chat->id, $body['session_id']);
        $this->assertTrue($body['enable_memory']);
        $this->assertSame('Responde breve.', $body['user_prompt']);
        $this->assertCount(2, $body['conversation_history']);
    }

    public function test_it_omits_empty_user_prompt_and_history(): void
    {
        $appUser = AppUser::factory()->create();

        $body = RagQueryStreamBodyHelper::build(
            sessionId: (string) $appUser->id,
            query: 'Hola',
            mode: 'hybrid',
            source: 'chat',
            responseType: 'paragraph',
            history: [],
        );

        $this->assertSame('chat', $body['source']);
        $this->assertSame((string) $appUser->id, $body['session_id']);
        $this->assertArrayNotHasKey('user_prompt', $body);
        $this->assertArrayNotHasKey('conversation_history', $body);
    }
}

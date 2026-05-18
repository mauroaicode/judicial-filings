<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\AiChat\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
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

    public function test_it_returns_rag_answer_without_modifying_text(): void
    {
        Queue::fake();

        Http::fake([
            '*/query?tenant_id=*' => Http::response([
                'answer' => 'El proceso tiene **actuaciones** pendientes.\n\n### Referencias\n- [1] doc.pdf',
            ]),
        ]);

        $payload = [
            'content' => '¿Qué dice el proceso?',
        ];

        $response = $this->actingAs($this->appUser, 'sanctum')
            ->postJson("/api/app-user/ai-chats/{$this->chat->id}/voice", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath(
            'answer',
            'El proceso tiene **actuaciones** pendientes.\n\n### Referencias\n- [1] doc.pdf'
        );
        $response->assertJsonStructure([
            'answer',
            'user_message_id',
            'assistant_message_id',
        ]);
        expect($response->json('user_message_id'))->not->toBeEmpty();
        expect($response->json('assistant_message_id'))->not->toBeEmpty();

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_id' => $this->chat->id,
            'role' => 'user',
            'content' => '¿Qué dice el proceso?',
        ]);

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_id' => $this->chat->id,
            'role' => 'assistant',
            'content' => 'El proceso tiene **actuaciones** pendientes.\n\n### Referencias\n- [1] doc.pdf',
        ]);

        Queue::assertPushed(UpdateAiChatTitleJob::class);
    }

    public function test_it_uses_naive_mode_for_rag_query(): void
    {
        Queue::fake();

        Http::fake(function ($request) {
            expect($request->data()['mode'])->toBe('naive');
            expect(strlen((string) $request->data()['user_prompt']))->toBeLessThanOrEqual(1000);

            return Http::response(['answer' => 'Respuesta de prueba.']);
        });

        $this->actingAs($this->appUser, 'sanctum')
            ->postJson("/api/app-user/ai-chats/{$this->chat->id}/voice", [
                'content' => '¿Estado del proceso?',
            ])
            ->assertStatus(200);
    }

    public function test_it_returns_502_when_rag_fails(): void
    {
        Http::fake([
            '*/query?tenant_id=*' => Http::response(['detail' => 'error'], 500),
        ]);

        $response = $this->actingAs($this->appUser, 'sanctum')
            ->postJson("/api/app-user/ai-chats/{$this->chat->id}/voice", [
                'content' => 'Hola',
            ]);

        $response->assertStatus(502);
    }
}

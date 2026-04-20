<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\AiChat\Controllers;

use Illuminate\Support\Facades\Queue;
use Src\Application\AppUser\AiChat\Jobs\UpdateAiChatTitleJob;
use Src\Domain\AiChat\Models\AiChat;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Tests\TestCase;

class AiChatStreamControllerTest extends TestCase
{
    private AppUser $appUser;
    private Organization $organization;
    private Process $process;
    private AiChat $chat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
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

    public function test_it_saves_user_message_and_dispatches_title_job_on_first_message(): void
    {
        Queue::fake();

        $payload = [
            'content' => '¿Cuál es el estado del proceso?',
            'search_mode' => 'strategic',
        ];

        // Nota: El test fallará en la parte del streaming real si el RAG API no está encendido,
        // pero podemos verificar que el mensaje se guardó ANTES de que el servicio intente conectar.
        // Sin embargo, como el servicio es síncrono en la parte inicial...
        
        // Vamos a simular la petición.
        $response = $this->actingAs($this->appUser, 'sanctum')
            ->postJson("/api/app-user/ai-chats/{$this->chat->id}/stream", $payload);

        // El status será 200 porque StreamedResponse retorna 200 inmediatamente
        $response->assertStatus(200);

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_id' => $this->chat->id,
            'role' => 'user',
            'content' => '¿Cuál es el estado del proceso?',
            'search_mode' => 'strategic',
        ]);

        Queue::assertPushed(UpdateAiChatTitleJob::class);
    }
}

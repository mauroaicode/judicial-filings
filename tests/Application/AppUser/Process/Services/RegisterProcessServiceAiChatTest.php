<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\Process\Services;

use Mockery;
use Src\Application\AppUser\Process\Services\RegisterProcessService;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Application\Shared\Services\Process\ProcessSyncService;
use Src\Domain\AiChat\Models\AiChat;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Tests\TestCase;

class RegisterProcessServiceAiChatTest extends TestCase
{
    private RegisterProcessService $service;
    private Organization $organization;
    private AppUser $appUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->appUser = AppUser::factory()->create();
        $this->appUser->organizations()->attach($this->organization->id);

        $consultMock = Mockery::mock(JudicialBranchConsultService::class);
        $syncMock = Mockery::mock(ProcessSyncService::class);

        $this->service = new RegisterProcessService($consultMock, $syncMock);
    }

    public function test_it_creates_an_initial_ai_chat_when_attaching_existing_process(): void
    {
        $process = Process::factory()->public()->create();

        // Ensure no chat exists
        $this->assertDatabaseMissing('ai_chats', [
            'process_id' => $process->id,
            'organization_id' => $this->organization->id,
        ]);

        $this->service->handle(
            processNumber: $process->process_number,
            organizationId: $this->organization->id,
            appUserId: $this->appUser->id
        );

        $this->assertDatabaseHas('ai_chats', [
            'process_id' => $process->id,
            'organization_id' => $this->organization->id,
            'app_user_id' => $this->appUser->id,
            'title' => 'Chat Inicial',
            'is_private' => false,
        ]);
    }
}

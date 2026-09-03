<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\Process\Services;

use Mockery;
use Src\Application\AppUser\Process\Services\RegisterProcessService;
use Src\Application\Shared\Process\Timeline\Contracts\ProcessTimelineRecorder;
use Src\Application\Shared\Process\Timeline\Services\RecordSemaphoreTimelineEventService;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Application\Shared\Services\Process\ProcessActionAlertNotificationService;
use Src\Application\Shared\Services\Process\ProcessSourceFallbackService;
use Src\Application\Shared\Services\Process\ProcessSyncService;
use Src\Application\Shared\Services\SamaiConsultService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessTimelineEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
        $consultMock->shouldReceive('withSeed')->byDefault()->andReturnSelf();
        $consultMock->shouldReceive('fetchProcesses')->byDefault()->andReturn((object) [
            'isSuccessful' => true,
            'data' => [],
        ]);

        $syncMock = Mockery::mock(ProcessSyncService::class);
        $syncMock->shouldReceive('syncForRegistration')->byDefault();
        $syncMock->shouldReceive('notifyPrivacyTransitionForAdmin')->byDefault();

        // Real fallback (readonly — no Mockery). With empty JB list above, migrate is not invoked.
        $this->service = new RegisterProcessService(
            $consultMock,
            $syncMock,
            app(ProcessSourceFallbackService::class),
            app(ProcessTimelineRecorder::class),
            app(\Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService::class),
        );
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

    public function test_it_skips_ai_chat_when_app_user_id_is_not_an_app_user_row(): void
    {
        $process = Process::factory()->public()->create();

        $this->service->handle(
            processNumber: $process->process_number,
            organizationId: $this->organization->id,
            appUserId: 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'
        );

        $this->assertDatabaseMissing('ai_chats', [
            'process_id' => $process->id,
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_it_records_privacy_detected_while_registering_an_existing_process(): void
    {
        // Deterministic administrativo radicado so SAMAI fallback is always consulted.
        $process = Process::factory()->public()->create([
            'process_number' => '76001333302020250025700',
            'court' => 'JUZGADO 020 ADMINISTRATIVO DE CALI',
        ]);

        $consult = Mockery::mock(JudicialBranchConsultService::class);
        $consult->shouldReceive('withSeed')->once()->andReturnSelf();
        $consult->shouldReceive('fetchProcesses')->once()->andReturn((object) [
            'isSuccessful' => true,
            'data' => [[
                'idProceso' => $process->process_id,
                'esPrivado' => true,
            ]],
        ]);

        $sync = Mockery::mock(ProcessSyncService::class);
        $sync->shouldReceive('notifyPrivacyTransitionForAdmin')->once();

        $samai = Mockery::mock(SamaiConsultService::class);
        $samai->shouldReceive('withSeed')->once()->andReturnSelf();
        $samai->shouldReceive('buscarProceso')->once()->andReturn([]);

        $fallback = new ProcessSourceFallbackService(
            $samai,
            app(ProcessActionAlertNotificationService::class),
            app(ProcessTimelineRecorder::class),
            app(RecordSemaphoreTimelineEventService::class),
        );

        $service = new RegisterProcessService(
            $consult,
            $sync,
            $fallback,
            app(ProcessTimelineRecorder::class),
            app(\Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService::class),
        );

        try {
            $service->handle($process->process_number, $this->organization->id);
            $this->fail('Expected registration to reject the still-private process.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $event = ProcessTimelineEvent::query()
            ->where('process_id', $process->id)
            ->where('event_type', ProcessTimelineEventType::PROCESS_BECAME_PRIVATE->value)
            ->first();

        $this->assertNotNull($event);
        $this->assertNull($event->organization_id);
        $this->assertSame('judicial_branch_api_reported_private', $event->payload['reason']);
    }
}

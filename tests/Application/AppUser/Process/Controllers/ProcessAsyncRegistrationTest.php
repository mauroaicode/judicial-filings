<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Src\Application\AppUser\Process\Jobs\GenerateProcessAiSummaryJob;
use Src\Application\AppUser\Process\Jobs\SyncJudicialBranchJob;
use Src\Application\Shared\Services\AiRagService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;
use Src\Domain\Notification\Notifications\ProcessAiSummaryReadyNotification;
use Src\Domain\Notification\Notifications\ProcessDataImportedNotification;
use Src\Domain\Notification\Notifications\ProcessImportFailedNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessRegistrationLog;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);

    // Attach user to organization
    $this->appUser->organizations()->attach($this->organization->id, [
        'is_owner' => true,
    ]);

    JudicialSyncRun::query()->delete();
});

it('dispatches the process registration flow asynchronously without placeholders', function (): void {
    Queue::fake();

    $processNumber = '76001333301320998888001';
    $peekProcessId = 6060606060;

    Http::fake([
        config('judicial-branch.api_url').'/Procesos/Consulta/NumeroRadicacion*' => Http::response([
            'procesos' => [
                [
                    'idProceso' => $peekProcessId,
                    'esPrivado' => false,
                ],
            ],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
        config('judicial-branch.api_url')."/Proceso/Actuaciones/{$peekProcessId}*" => Http::response([
            'actuaciones' => [],
            'paginacion' => [
                'cantidadPaginas' => 10,
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes', [
            'process_number' => $processNumber,
            'lawyer_role' => 'plaintiff',
        ]);

    $response->assertStatus(201);
    $response->assertJsonFragment(['message' => __('process.registration_dispatched')]);

    // Verify Job was dispatched with the number on the process-import door
    Queue::assertPushed(SyncJudicialBranchJob::class, function ($job) use ($processNumber) {
        return $job->processNumber === $processNumber
            && $job->organizationId === $this->organization->id
            && $job->appUser->id === $this->appUser->id
            && $job->lawyerRole === ProcessLawyerRole::PLAINTIFF
            && $job->queue === config('process-import.jobs.import_radicado.queue');
    });

    // Verify Log was created
    $log = ProcessRegistrationLog::where('process_number', $processNumber)->first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('pending');
});

it('successfully runs SyncJudicialBranchJob, creates process and dispatches AI job', function (): void {
    Queue::fake();
    Notification::fake();
    Config::set('ia-rag.enabled', true);

    $this->mock(AiRagService::class, function ($mock) {
        $mock->shouldReceive('uploadMarkdown')->andReturn(true);
    });

    $processNumber = '76001333301320170009301';

    // Create log manually for the job to update
    ProcessRegistrationLog::create([
        'organization_id' => $this->organization->id,
        'app_user_id' => $this->appUser->id,
        'process_number' => $processNumber,
        'status' => 'pending',
    ]);

    // Mock API for RegisterProcessService
    Http::fake([
        '*Procesos/Consulta/NumeroRadicacion*' => Http::response([
            'procesos' => [['idProceso' => 12345678, 'esPrivado' => false]],
            'paginacion' => ['cantidadPaginas' => 1],
        ], 200),
        '*Proceso/Detalle/12345678' => Http::response([
            'idProceso' => 12345678,
            'despacho' => 'DESPACHO TEST',
            'departamento' => 'TEST',
            'tipoProceso' => 'Test',
            'claseProceso' => 'Test',
            'fechaProceso' => '2024-01-01',
            'esPrivado' => false,
        ], 200),
        '*Proceso/Actuaciones/12345678*' => Http::response([
            'actuaciones' => [],
            'paginacion' => ['cantidadPaginas' => 1],
        ], 200),
        '*Proceso/Sujetos/12345678*' => Http::response([
            'sujetos' => [],
            'paginacion' => ['cantidadPaginas' => 1],
        ], 200),
    ]);

    $job = new SyncJudicialBranchJob($processNumber, $this->organization->id, $this->appUser);
    app()->call([$job, 'handle']);

    // Verify Process exists in DB
    $process = Process::where('process_number', $processNumber)->first();
    expect($process)->not->toBeNull()
        ->and($process->status)->toBe('activo');

    // Verify Log updated
    $log = ProcessRegistrationLog::where('process_number', $processNumber)->first();
    expect($log->status)->toBe('success');

    // Verify Notification sent
    Notification::assertSentTo(
        $this->appUser,
        ProcessDataImportedNotification::class,
        function ($notification) use ($process) {
            return $notification->toDatabase($this->appUser)['id'] === $process->id;
        }
    );

    // Verify AI Job dispatched
    Queue::assertPushed(GenerateProcessAiSummaryJob::class);
});

it('notifies failure when SyncJudicialBranchJob fails', function (): void {
    Notification::fake();
    Config::set('process-import.retry_max_attempts_for_not_found', 0);
    Config::set('process-import.retry_max_attempts_for_empty', 0);
    Config::set('process-import.retry_max_attempts', 0);

    $processNumber = '76001333301320170000000'; // Fake non-existent

    // Create log manually
    ProcessRegistrationLog::create([
        'organization_id' => $this->organization->id,
        'app_user_id' => $this->appUser->id,
        'process_number' => $processNumber,
        'status' => 'pending',
    ]);

    // Mock API failure
    Http::fake([
        '*Procesos/Consulta/NumeroRadicacion*' => Http::response(['procesos' => []], 200),
    ]);

    $job = new SyncJudicialBranchJob($processNumber, $this->organization->id, $this->appUser);

    $caught = null;
    try {
        app()->call([$job, 'handle']);
    } catch (\Throwable $e) {
        $caught = $e;
        $job->failed($e);
    }

    expect($caught)->not->toBeNull();

    // Verify Log updated to failed
    $log = ProcessRegistrationLog::where('process_number', $processNumber)->first();
    expect($log->status)->toBe('failed');

    // Verify Failure Notification sent
    Notification::assertSentTo(
        $this->appUser,
        ProcessImportFailedNotification::class
    );
});

it('dispatches SyncJudicialBranchJob for a public radicado while a judicial sync batch is active', function (): void {
    Queue::fake();

    JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::BatchPending,
        'started_at' => now()->subMinutes(10),
        'data_source' => JudicialSyncDataSource::JudicialBranch,
    ]);

    $processNumber = '76520310500320260014400';
    $processId = 6060606077;

    Http::fake([
        config('judicial-branch.api_url').'/Procesos/Consulta/NumeroRadicacion*' => Http::response([
            'procesos' => [
                [
                    'idProceso' => $processId,
                    'esPrivado' => false,
                    'llaveProceso' => $processNumber,
                ],
            ],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes', [
            'process_number' => $processNumber,
            'lawyer_role' => 'plaintiff',
        ]);

    $response->assertStatus(201);
    $response->assertJsonFragment(['message' => __('process.registration_dispatched')]);

    Queue::assertPushed(SyncJudicialBranchJob::class, function ($job) use ($processNumber) {
        return $job->processNumber === $processNumber
            && $job->queue === config('process-import.jobs.import_radicado.queue');
    });
});

it('returns the manual registration modal for a private radicado while a judicial sync batch is active', function (): void {
    Queue::fake();

    JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::BatchPending,
        'started_at' => now()->subMinutes(10),
        'data_source' => JudicialSyncDataSource::JudicialBranch,
    ]);

    $processNumber = '76520310500320260015500';

    Http::fake([
        config('judicial-branch.api_url').'/Procesos/Consulta/NumeroRadicacion*' => Http::response([
            'procesos' => [
                [
                    'idProceso' => 6060606088,
                    'esPrivado' => true,
                    'llaveProceso' => $processNumber,
                ],
            ],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes', [
            'process_number' => $processNumber,
            'lawyer_role' => 'defendant',
        ]);

    $response->assertStatus(202);
    $response->assertJson([
        'status' => 'manual_registration_required',
        'requires_details' => true,
        'process_number' => $processNumber,
    ]);

    Queue::assertNotPushed(SyncJudicialBranchJob::class);
});

it('attaches an existing public process inline while a judicial sync batch is active', function (): void {
    Queue::fake();

    JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::BatchPending,
        'started_at' => now()->subMinutes(10),
        'data_source' => JudicialSyncDataSource::JudicialBranch,
    ]);

    $processNumber = '76520310500320260016600';
    Process::factory()->public()->create([
        'process_number' => $processNumber,
        'process_id' => 6060606099,
    ]);

    Http::fake();

    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes', [
            'process_number' => $processNumber,
            'lawyer_role' => 'plaintiff',
        ]);

    $response->assertStatus(201);
    $response->assertJsonFragment(['message' => __('process.registered_successfully')]);

    Queue::assertNotPushed(SyncJudicialBranchJob::class);
    Http::assertNothingSent();
});

it('returns the manual registration modal for an existing private process while a judicial sync batch is active', function (): void {
    Queue::fake();

    JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::BatchPending,
        'started_at' => now()->subMinutes(10),
        'data_source' => JudicialSyncDataSource::JudicialBranch,
    ]);

    $processNumber = '76520310500320260017700';
    Process::factory()->private()->create([
        'process_number' => $processNumber,
        'process_id' => 6060606100,
    ]);

    Http::fake();

    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes', [
            'process_number' => $processNumber,
            'lawyer_role' => 'defendant',
        ]);

    $response->assertStatus(202);
    $response->assertJson([
        'status' => 'manual_registration_required',
        'reason' => 'private',
        'requires_details' => true,
        'process_number' => $processNumber,
    ]);

    Queue::assertNotPushed(SyncJudicialBranchJob::class);
    Http::assertNothingSent();
});

it('successfully runs GenerateProcessAiSummaryJob and saves summary', function (): void {
    Notification::fake();

    $this->mock(AiRagService::class, function ($mock) {
        $mock->shouldReceive('uploadMarkdown')->andReturn('task_id');
        $mock->shouldReceive('querySummary')->andReturn(['resumen' => 'Este es un resumen test']);
    });

    $process = Process::factory()->create([
        'process_number' => '76001333301320170009301',
        'status' => 'activo',
    ]);

    // Mock RAG Engine
    Http::fake([
        'http://localhost:8000/insert' => Http::response(['task_id' => 'task_123'], 200),
        'http://localhost:8000/task/task_123' => Http::response(['status' => 'completed'], 200),
        'http://localhost:8000/query' => Http::response(['hitos' => '...', 'resumen' => 'Este es un resumen test'], 200),
    ]);

    $job = new GenerateProcessAiSummaryJob($process, $this->organization->id, $this->appUser);
    app()->call([$job, 'handle']);

    $process->refresh();
    expect($process->ai_summary)->not->toBeNull()
        ->and($process->ai_summary['resumen'])->toBe('Este es un resumen test');

    // Verify Notification sent
    Notification::assertSentTo(
        $this->appUser,
        ProcessAiSummaryReadyNotification::class
    );
});

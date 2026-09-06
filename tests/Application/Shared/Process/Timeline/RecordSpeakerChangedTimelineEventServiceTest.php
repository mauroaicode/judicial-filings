<?php

declare(strict_types=1);

use Src\Application\Shared\Process\Timeline\Presenters\ProcessTimelineEventPresenter;
use Src\Application\Shared\Process\Timeline\Services\RecordSpeakerChangedTimelineEventService;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Application\Shared\Services\Process\ProcessSyncService;
use Src\Application\Shared\Services\SamaiConsultService;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessDataSource;
use Src\Domain\Process\Models\ProcessTimelineEvent;

afterEach(function (): void {
    Mockery::close();
});

it('records speaker_changed timeline when ponente changes from A to B', function (): void {
    $process = Process::factory()->create([
        'speaker' => 'Juan Pablo Dossman Cortez',
    ]);

    app(RecordSpeakerChangedTimelineEventService::class)->handle(
        $process,
        'Juan Pablo Dossman Cortez',
        'Carlos Andrés Zambrano San Juan',
    );

    $event = ProcessTimelineEvent::query()
        ->where('process_id', $process->id)
        ->where('event_type', ProcessTimelineEventType::SPEAKER_CHANGED->value)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->payload['from'])->toBe('Juan Pablo Dossman Cortez')
        ->and($event->payload['to'])->toBe('Carlos Andrés Zambrano San Juan');

    $display = ProcessTimelineEventPresenter::for($event);

    expect($display['title'])->toBe(__('process_timeline.event_types.speaker_changed'))
        ->and($display['from'])->toBe('Juan Pablo Dossman Cortez')
        ->and($display['to'])->toBe('Carlos Andrés Zambrano San Juan')
        ->and($display['summary'])->toBe(__('process_timeline.summaries.speaker_changed', [
            'from' => 'Juan Pablo Dossman Cortez',
            'to' => 'Carlos Andrés Zambrano San Juan',
        ]))
        ->and(collect($display['dates'])->pluck('key')->all())->toContain('speaker_changed_at');
});

it('does not record speaker_changed on first fill from empty', function (): void {
    $process = Process::factory()->create(['speaker' => null]);

    app(RecordSpeakerChangedTimelineEventService::class)->handle(
        $process,
        null,
        'Carlos Andrés Zambrano San Juan',
    );

    expect(
        ProcessTimelineEvent::query()
            ->where('process_id', $process->id)
            ->where('event_type', ProcessTimelineEventType::SPEAKER_CHANGED->value)
            ->exists()
    )->toBeFalse();
});

it('does not record speaker_changed when from and to are the same', function (): void {
    $process = Process::factory()->create([
        'speaker' => 'Carlos Andrés Zambrano San Juan',
    ]);

    app(RecordSpeakerChangedTimelineEventService::class)->handle(
        $process,
        'Carlos Andrés Zambrano San Juan',
        'Carlos Andrés Zambrano San Juan',
    );

    expect(
        ProcessTimelineEvent::query()
            ->where('process_id', $process->id)
            ->where('event_type', ProcessTimelineEventType::SPEAKER_CHANGED->value)
            ->exists()
    )->toBeFalse();
});

it('records speaker_changed during judicial branch discovery when ponente changes', function (): void {
    $processNumber = '76001233300920170037700';
    $apiProcessId = 88001234;

    $organization = Organization::factory()->create(['is_active' => true]);
    $process = Process::factory()->create([
        'process_number' => $processNumber,
        'process_id' => $apiProcessId,
        'speaker' => 'Juan Pablo Dossman Cortez',
        'process_data_source_id' => ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::JudicialBranch),
        'is_manual_sync' => false,
        'is_private' => false,
    ]);
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => OrganizationProcessStatus::ACTIVE->value,
    ]);

    $jb = Mockery::mock(JudicialBranchConsultService::class)->shouldIgnoreMissing();
    $jb->shouldReceive('withSeed')->with($processNumber)->andReturnSelf();
    $jb->shouldReceive('fetchProcesses')->with($processNumber)->andReturn((object) [
        'isSuccessful' => true,
        'data' => [[
            'idProceso' => $apiProcessId,
            'llaveProceso' => $processNumber,
            'despacho' => $process->court,
            'ponente' => 'Carlos Andrés Zambrano San Juan',
            'departamento' => $process->department,
            'esPrivado' => false,
            'fechaUltimaActuacion' => now()->toDateString(),
        ]],
    ]);
    $jb->shouldReceive('fetchActionByProcess')->andReturn((object) [
        'isSuccessful' => false,
        'data' => [],
    ]);
    $jb->shouldReceive('fetchDetail')->andReturn((object) ['isSuccessful' => false, 'data' => []]);
    $jb->shouldReceive('fetchSubjects')->andReturn((object) ['isSuccessful' => false, 'data' => []]);

    $samai = Mockery::mock(SamaiConsultService::class)->shouldIgnoreMissing();

    $this->app->instance(JudicialBranchConsultService::class, $jb);
    $this->app->instance(SamaiConsultService::class, $samai);

    $sync = app(ProcessSyncService::class);
    $method = new ReflectionMethod(ProcessSyncService::class, 'discoverNewProcesses');
    $method->setAccessible(true);
    $method->invoke($sync, $processNumber);

    $process->refresh();

    expect($process->speaker)->toBe('Carlos Andrés Zambrano San Juan');

    $event = ProcessTimelineEvent::query()
        ->where('process_id', $process->id)
        ->where('event_type', ProcessTimelineEventType::SPEAKER_CHANGED->value)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->payload['from'])->toBe('Juan Pablo Dossman Cortez')
        ->and($event->payload['to'])->toBe('Carlos Andrés Zambrano San Juan')
        ->and($event->source->value)->toBe('judicial_branch');
});

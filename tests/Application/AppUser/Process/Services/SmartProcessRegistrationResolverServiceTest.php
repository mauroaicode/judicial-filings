<?php

declare(strict_types=1);

use Src\Application\AppUser\Process\Services\SmartProcessRegistrationResolverService;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Application\Shared\Services\SamaiConsultService;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Models\Process;

beforeEach(function (): void {
    JudicialSyncRun::query()->delete();
    $this->organization = Organization::factory()->create();
    $this->processNumber = '76520310500320260013300';
    Process::query()->whereIn('process_number', [
        $this->processNumber,
        '76001333301320160005700',
    ])->delete();
});

afterEach(function (): void {
    Mockery::close();
});

it('defers judicial branch registration to the queue while a sync batch is active', function (): void {
    JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::BatchPending,
        'started_at' => now()->subMinutes(30),
        'data_source' => JudicialSyncDataSource::JudicialBranch,
    ]);

    $jb = Mockery::mock(JudicialBranchConsultService::class);
    $jb->shouldReceive('withSeed')->once()->with($this->processNumber)->andReturnSelf();
    $jb->shouldReceive('fetchProcesses')->once()->andReturn((object) [
        'isSuccessful' => true,
        'data' => [[
            'idProceso' => 999001,
            'esPrivado' => false,
            'llaveProceso' => $this->processNumber,
        ]],
    ]);
    $jb->shouldReceive('peekActuacionesPagination')->once()->with(999001)->andReturn((object) [
        'isSuccessful' => true,
        'totalPages' => 1,
    ]);

    $samai = Mockery::mock(SamaiConsultService::class);

    $decision = (new SmartProcessRegistrationResolverService($jb, $samai))
        ->handle($this->processNumber, $this->organization->id);

    expect($decision->source)->toBe(ProcessDataSourceSlug::JudicialBranch)
        ->and($decision->deferToQueue)->toBeTrue();
});

it('keeps short judicial branch registrations inline when sync is idle', function (): void {
    $jb = Mockery::mock(JudicialBranchConsultService::class);
    $jb->shouldReceive('withSeed')->once()->with($this->processNumber)->andReturnSelf();
    $jb->shouldReceive('fetchProcesses')->once()->andReturn((object) [
        'isSuccessful' => true,
        'data' => [[
            'idProceso' => 999002,
            'esPrivado' => false,
            'llaveProceso' => $this->processNumber,
        ]],
    ]);
    $jb->shouldReceive('peekActuacionesPagination')->once()->with(999002)->andReturn((object) [
        'isSuccessful' => true,
        'totalPages' => 1,
    ]);

    $samai = Mockery::mock(SamaiConsultService::class);

    $decision = (new SmartProcessRegistrationResolverService($jb, $samai))
        ->handle($this->processNumber, $this->organization->id);

    expect($decision->source)->toBe(ProcessDataSourceSlug::JudicialBranch)
        ->and($decision->deferToQueue)->toBeFalse();
});

it('defers attaching an existing process while its source sync batch is active', function (): void {
    JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::Started,
        'started_at' => now()->subMinutes(5),
        'data_source' => JudicialSyncDataSource::JudicialBranch,
    ]);

    Process::factory()->create([
        'process_number' => $this->processNumber,
        'process_id' => 888001,
    ]);

    $jb = Mockery::mock(JudicialBranchConsultService::class);
    $samai = Mockery::mock(SamaiConsultService::class);

    $decision = (new SmartProcessRegistrationResolverService($jb, $samai))
        ->handle($this->processNumber, $this->organization->id);

    expect($decision->source)->toBe(ProcessDataSourceSlug::JudicialBranch)
        ->and($decision->deferToQueue)->toBeTrue();
});

it('does not consult SAMAI when Unificada misses a laboral radicado', function (): void {
    $jb = Mockery::mock(JudicialBranchConsultService::class);
    $jb->shouldReceive('withSeed')->once()->with($this->processNumber)->andReturnSelf();
    $jb->shouldReceive('fetchProcesses')->once()->andReturn((object) [
        'isSuccessful' => true,
        'data' => [],
    ]);

    $samai = Mockery::mock(SamaiConsultService::class);
    $samai->shouldNotReceive('buscarProceso');

    expect(fn () => (new SmartProcessRegistrationResolverService($jb, $samai))
        ->handle($this->processNumber, $this->organization->id))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('consults SAMAI when Unificada misses an administrative radicado', function (): void {
    $adminNumber = '76001333301320160005700';

    $jb = Mockery::mock(JudicialBranchConsultService::class);
    $jb->shouldReceive('withSeed')->once()->with($adminNumber)->andReturnSelf();
    $jb->shouldReceive('fetchProcesses')->once()->andReturn((object) [
        'isSuccessful' => true,
        'data' => [],
    ]);

    $samai = Mockery::mock(SamaiConsultService::class);
    $samai->shouldReceive('withSeed')->once()->with($adminNumber)->andReturnSelf();
    $samai->shouldReceive('buscarProceso')->once()->andReturn([]);

    expect(fn () => (new SmartProcessRegistrationResolverService($jb, $samai))
        ->handle($adminNumber, $this->organization->id))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

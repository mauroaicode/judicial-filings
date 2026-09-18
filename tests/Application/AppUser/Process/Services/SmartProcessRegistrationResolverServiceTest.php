<?php

declare(strict_types=1);

use Src\Application\AppUser\Process\Services\SmartProcessRegistrationResolverService;
use Src\Application\Shared\Exceptions\ManualRegistrationRequiredException;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Application\Shared\Services\SamaiConsultService;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ManualRegistrationRequestReason;
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

it('defers public judicial branch registration after probing while a sync batch is active', function (): void {
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
            'idProceso' => 999002,
            'esPrivado' => false,
            'llaveProceso' => $this->processNumber,
        ]],
    ]);
    $jb->shouldNotReceive('peekActuacionesPagination');

    $samai = Mockery::mock(SamaiConsultService::class);
    $samai->shouldNotReceive('buscarProceso');

    $decision = (new SmartProcessRegistrationResolverService($jb, $samai))
        ->handle($this->processNumber, $this->organization->id);

    expect($decision->source)->toBe(ProcessDataSourceSlug::JudicialBranch)
        ->and($decision->deferToQueue)->toBeTrue();
});

it('requires manual registration for a private radicado while a sync batch is active', function (): void {
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
            'idProceso' => 999003,
            'esPrivado' => true,
            'llaveProceso' => $this->processNumber,
        ]],
    ]);
    $jb->shouldNotReceive('peekActuacionesPagination');

    $samai = Mockery::mock(SamaiConsultService::class);
    $samai->shouldNotReceive('buscarProceso');

    try {
        (new SmartProcessRegistrationResolverService($jb, $samai))
            ->handle($this->processNumber, $this->organization->id);
        expect(false)->toBeTrue();
    } catch (ManualRegistrationRequiredException $e) {
        expect($e->reason)->toBe(ManualRegistrationRequestReason::NotFound)
            ->and($e->processNumber)->toBe($this->processNumber);
    }
});

it('defers SAMAI writes after probing while a SAMAI sync batch is active', function (): void {
    $adminNumber = '76001333301320160005700';

    JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::Started,
        'started_at' => now()->subMinutes(5),
        'data_source' => JudicialSyncDataSource::Samai,
    ]);

    $jb = Mockery::mock(JudicialBranchConsultService::class);
    $jb->shouldReceive('withSeed')->once()->with($adminNumber)->andReturnSelf();
    $jb->shouldReceive('fetchProcesses')->once()->andReturn((object) [
        'isSuccessful' => true,
        'data' => [],
    ]);

    $samai = Mockery::mock(SamaiConsultService::class);
    $samai->shouldReceive('withSeed')->once()->with($adminNumber)->andReturnSelf();
    $samai->shouldReceive('buscarProceso')->once()->andReturn([['id' => 1]]);
    $samai->shouldNotReceive('contarActuaciones');

    $decision = (new SmartProcessRegistrationResolverService($jb, $samai))
        ->handle($adminNumber, $this->organization->id);

    expect($decision->source)->toBe(ProcessDataSourceSlug::Samai)
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

it('attaches an existing public process inline while its source sync batch is active', function (): void {
    JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::Started,
        'started_at' => now()->subMinutes(5),
        'data_source' => JudicialSyncDataSource::JudicialBranch,
    ]);

    Process::factory()->public()->create([
        'process_number' => $this->processNumber,
        'process_id' => 888001,
    ]);

    $jb = Mockery::mock(JudicialBranchConsultService::class);
    $samai = Mockery::mock(SamaiConsultService::class);

    $decision = (new SmartProcessRegistrationResolverService($jb, $samai))
        ->handle($this->processNumber, $this->organization->id);

    expect($decision->source)->toBe(ProcessDataSourceSlug::JudicialBranch)
        ->and($decision->deferToQueue)->toBeFalse();
});

it('requires manual registration for an existing private process while a sync batch is active', function (): void {
    JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::Started,
        'started_at' => now()->subMinutes(5),
        'data_source' => JudicialSyncDataSource::JudicialBranch,
    ]);

    Process::factory()->private()->create([
        'process_number' => $this->processNumber,
        'process_id' => 888002,
    ]);

    $jb = Mockery::mock(JudicialBranchConsultService::class);
    $samai = Mockery::mock(SamaiConsultService::class);

    try {
        (new SmartProcessRegistrationResolverService($jb, $samai))
            ->handle($this->processNumber, $this->organization->id);
        expect(false)->toBeTrue();
    } catch (ManualRegistrationRequiredException $e) {
        expect($e->reason)->toBe(ManualRegistrationRequestReason::Private)
            ->and($e->processNumber)->toBe($this->processNumber);
    }
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
        ->toThrow(\Src\Application\Shared\Exceptions\ManualRegistrationRequiredException::class);
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
        ->toThrow(\Src\Application\Shared\Exceptions\ManualRegistrationRequiredException::class);
});

it('defers judicial branch registration to the queue when the portal proxy times out', function (): void {
    $jb = Mockery::mock(JudicialBranchConsultService::class);
    $jb->shouldReceive('withSeed')->once()->with($this->processNumber)->andReturnSelf();
    $jb->shouldReceive('fetchProcesses')->once()->andThrow(
        new \Src\Application\Shared\Exceptions\ApiProxyFailureException('Proxy curl error on fetchProcesses: cURL error 28')
    );

    $samai = Mockery::mock(SamaiConsultService::class);
    $samai->shouldNotReceive('buscarProceso');

    $decision = (new SmartProcessRegistrationResolverService($jb, $samai))
        ->handle($this->processNumber, $this->organization->id);

    expect($decision->source)->toBe(ProcessDataSourceSlug::JudicialBranch)
        ->and($decision->deferToQueue)->toBeTrue();
});

<?php

declare(strict_types=1);

use Src\Application\Shared\Exceptions\ApiEmptyProcessesException;
use Src\Application\Shared\Jobs\SyncProcessJob;
use Src\Application\Shared\Services\Process\ProcessSyncService;
use Src\Domain\Process\Models\Process;

it('requeues a known radicado when the portal returns an empty list', function (): void {
    $process = Process::factory()->create([
        'process_number' => '76001310500220170063501',
        'process_id' => 1832590134,
    ]);

    $sync = Mockery::mock(ProcessSyncService::class);
    $sync->shouldReceive('syncByProcessNumber')
        ->once()
        ->with($process->process_number)
        ->andThrow(new ApiEmptyProcessesException('empty'));

    $job = new class($process->process_number) extends SyncProcessJob
    {
        public ?int $releasedDelay = null;

        public function release($delay = 0): void
        {
            $this->releasedDelay = (int) $delay;
        }
    };

    $job->handle($sync);

    expect($job->releasedDelay)->toBe(30);
});

it('does not retry an empty list when the radicado was never stored', function (): void {
    $sync = Mockery::mock(ProcessSyncService::class);
    $sync->shouldReceive('syncByProcessNumber')
        ->once()
        ->andThrow(new ApiEmptyProcessesException('empty'));

    $job = new class('99999999999999999999999') extends SyncProcessJob
    {
        public bool $released = false;

        public function release($delay = 0): void
        {
            $this->released = true;
        }
    };

    $job->handle($sync);

    expect($job->released)->toBeFalse();
});

it('stops retrying a known radicado after the last attempt', function (): void {
    $process = Process::factory()->create([
        'process_number' => '76001310500220170063502',
        'process_id' => 1832590135,
    ]);

    $sync = Mockery::mock(ProcessSyncService::class);
    $sync->shouldReceive('syncByProcessNumber')->once()->andThrow(new ApiEmptyProcessesException('empty'));

    $job = new class($process->process_number) extends SyncProcessJob
    {
        public bool $released = false;

        public function attempts(): int
        {
            return $this->tries;
        }

        public function release($delay = 0): void
        {
            $this->released = true;
        }
    };

    $job->handle($sync);

    expect($job->released)->toBeFalse();
});

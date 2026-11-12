<?php

declare(strict_types=1);

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Src\Application\Admin\JudicialSync\Jobs\QueueOrganizationJudicialSyncJob;
use Src\Application\Shared\Jobs\SyncProcessJob;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;

it('queues the organization judicial sync command as a SyncProcessJob batch', function (): void {
    Bus::fake();

    $organization = Organization::factory()->create();
    $process = Process::factory()->create([
        'process_number' => '76001333301320170019900',
        'is_manual_sync' => false,
    ]);
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    (new QueueOrganizationJudicialSyncJob($organization->id))->handle();

    Bus::assertBatched(function (PendingBatch $batch) use ($process): bool {
        $numbers = collect($batch->jobs)
            ->map(fn (SyncProcessJob $job): string => $job->processNumber)
            ->all();

        return $batch->name === 'Sync Judicial Processes Batch (organization)'
            && $numbers === [$process->process_number];
    });
});

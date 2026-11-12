<?php

declare(strict_types=1);

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Src\Application\Shared\Jobs\SyncProcessJob;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;

beforeEach(function (): void {
    JudicialSyncRun::query()->delete();
});

it('queues only the radicados of the organization, including shared ones', function (): void {
    Bus::fake();

    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $pivot = [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ];

    $shared = Process::factory()->create([
        'process_number' => '76001333301320170015500',
        'is_manual_sync' => false,
    ]);
    $onlyA = Process::factory()->create([
        'process_number' => '76001333301320170016600',
        'is_manual_sync' => false,
    ]);
    $onlyB = Process::factory()->create([
        'process_number' => '76001333301320170017700',
        'is_manual_sync' => false,
    ]);

    $shared->organizations()->attach([$organizationA->id => $pivot, $organizationB->id => $pivot]);
    $onlyA->organizations()->attach($organizationA->id, $pivot);
    $onlyB->organizations()->attach($organizationB->id, $pivot);

    $this->artisan('judicial:sync-processes-for-organization', [
        'organization' => $organizationA->id,
    ])->assertSuccessful();

    Bus::assertBatched(function (PendingBatch $batch) use ($shared, $onlyA): bool {
        $numbers = collect($batch->jobs)
            ->map(fn (SyncProcessJob $job): string => $job->processNumber)
            ->all();

        return $batch->name === 'Sync Judicial Processes Batch (organization)'
            && count($numbers) === 2
            && in_array($shared->process_number, $numbers, true)
            && in_array($onlyA->process_number, $numbers, true);
    });

    $run = JudicialSyncRun::query()->where('organization_id', $organizationA->id)->first();

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(JudicialSyncRunStatus::BatchPending)
        ->and($run->processes_queued)->toBe(2);
});

it('records a run with no processes when the organization has no radicados', function (): void {
    Bus::fake();

    $organization = Organization::factory()->create();

    $this->artisan('judicial:sync-processes-for-organization', [
        'organization' => $organization->id,
    ])->assertSuccessful();

    Bus::assertNothingBatched();

    $run = JudicialSyncRun::query()->where('organization_id', $organization->id)->first();

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(JudicialSyncRunStatus::NoProcesses);
});

it('rejects an unknown organization without queueing a run', function (): void {
    Bus::fake();

    $this->artisan('judicial:sync-processes-for-organization', [
        'organization' => (string) Str::uuid(),
    ])->assertFailed();

    Bus::assertNothingBatched();
    expect(JudicialSyncRun::query()->count())->toBe(0);
});

it('does not enqueue a second full-org batch while one is already pending', function (): void {
    Bus::fake();

    $organization = Organization::factory()->create();
    $process = Process::factory()->create([
        'process_number' => '76001333301320170018800',
        'is_manual_sync' => false,
    ]);
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    JudicialSyncRun::factory()->create([
        'organization_id' => $organization->id,
        'status' => JudicialSyncRunStatus::BatchPending,
        'started_at' => now()->subHour(),
        'data_source' => \Src\Domain\JudicialSync\Enums\JudicialSyncDataSource::JudicialBranch,
    ]);

    $this->artisan('judicial:sync-processes-for-organization', [
        'organization' => $organization->id,
    ])->assertSuccessful();

    Bus::assertNothingBatched();
    expect(JudicialSyncRun::query()->where('organization_id', $organization->id)->count())->toBe(1);
});

it('rejects an organization that cannot track processes', function (): void {
    Bus::fake();

    $organization = Organization::factory()->create([
        'is_process_tracking_enabled' => false,
    ]);

    $this->artisan('judicial:sync-processes-for-organization', [
        'organization' => $organization->id,
    ])->assertFailed();

    Bus::assertNothingBatched();
    expect(JudicialSyncRun::query()->count())->toBe(0);
});

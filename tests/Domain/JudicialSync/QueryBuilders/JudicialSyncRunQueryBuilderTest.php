<?php

declare(strict_types=1);

namespace Tests\Domain\JudicialSync\QueryBuilders;

use Illuminate\Support\Facades\DB;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;

it('orders runs by created_at descending then id descending', function (): void {
    $older = JudicialSyncRun::factory()->create([
        'started_at' => now()->subDays(5),
        'status' => JudicialSyncRunStatus::Started,
    ]);
    $older->timestamps = false;
    $older->forceFill(['created_at' => now()->subDays(5)])->save();

    $newer = JudicialSyncRun::factory()->create([
        'started_at' => now()->subDay(),
        'status' => JudicialSyncRunStatus::Started,
    ]);
    $newer->timestamps = false;
    $newer->forceFill(['created_at' => now()->subMinute()])->save();

    $results = JudicialSyncRun::query()
        ->whereIn('id', [$older->id, $newer->id])
        ->orderedByCreatedAtDesc()
        ->get();

    expect($results->first()->id)->toBe($newer->id);
    expect($results->last()->id)->toBe($older->id);
});

it('orders runs by started_at descending then id descending', function (): void {
    $older = JudicialSyncRun::factory()->create([
        'started_at' => now()->subDays(2),
        'status' => JudicialSyncRunStatus::Started,
    ]);
    $newer = JudicialSyncRun::factory()->create([
        'started_at' => now()->subDay(),
        'status' => JudicialSyncRunStatus::Started,
    ]);

    $results = JudicialSyncRun::query()
        ->whereIn('id', [$older->id, $newer->id])
        ->orderedByStartedAtDesc()
        ->get();

    expect($results->first()->id)->toBe($newer->id);
    expect($results->last()->id)->toBe($older->id);
});

it('filters runs by status value', function (): void {
    $match = JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::BatchCompleted,
    ]);
    JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::DispatchFailed,
    ]);

    $result = JudicialSyncRun::query()
        ->whereStatusValue(JudicialSyncRunStatus::BatchCompleted->value)
        ->whereKey($match->id)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($match->id);
});

it('filters runs by started_at date range', function (): void {
    $inside = JudicialSyncRun::factory()->create([
        'started_at' => now()->setDate(2026, 5, 15)->startOfDay(),
        'status' => JudicialSyncRunStatus::Started,
    ]);
    $outside = JudicialSyncRun::factory()->create([
        'started_at' => now()->setDate(2026, 4, 1)->startOfDay(),
        'status' => JudicialSyncRunStatus::Started,
    ]);

    $results = JudicialSyncRun::query()
        ->whereIn('id', [$inside->id, $outside->id])
        ->whereStartedAtBetween('2026-05-01', '2026-05-31')
        ->pluck('id')
        ->all();

    expect($results)->toContain($inside->id);
    expect($results)->not->toContain($outside->id);
});

it('does not apply date filters when from and to are empty', function (): void {
    $run = JudicialSyncRun::factory()->create([
        'started_at' => now()->setDate(2025, 1, 1)->startOfDay(),
        'status' => JudicialSyncRunStatus::Started,
    ]);

    $found = JudicialSyncRun::query()
        ->whereKey($run->id)
        ->whereStartedAtBetween(null, '')
        ->first();

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($run->id);
});

it('exposes queue pending jobs from job_batches join', function (): void {
    $batchId = 'test-batch-'.uniqid();

    DB::table('job_batches')->insert([
        'id' => $batchId,
        'name' => 'Judicial sync test batch',
        'total_jobs' => 10,
        'pending_jobs' => 4,
        'failed_jobs' => 0,
        'failed_job_ids' => '[]',
        'options' => null,
        'cancelled_at' => null,
        'created_at' => time(),
        'finished_at' => null,
    ]);

    $run = JudicialSyncRun::factory()->create([
        'laravel_batch_id' => $batchId,
        'status' => JudicialSyncRunStatus::BatchPending,
    ]);

    $result = JudicialSyncRun::query()
        ->whereKey($run->id)
        ->withQueuePendingJobs()
        ->first();

    expect($result)->not->toBeNull();
    expect($result->queue_pending_jobs)->toBe(4);
});

it('returns null pending jobs when no laravel batch is linked', function (): void {
    $run = JudicialSyncRun::factory()->create([
        'laravel_batch_id' => null,
        'status' => JudicialSyncRunStatus::NoProcesses,
    ]);

    $result = JudicialSyncRun::query()
        ->whereKey($run->id)
        ->withQueuePendingJobs()
        ->first();

    expect($result)->not->toBeNull();
    expect($result->queue_pending_jobs)->toBeNull();
});

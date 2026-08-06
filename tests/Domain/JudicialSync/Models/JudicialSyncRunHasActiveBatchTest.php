<?php

declare(strict_types=1);

use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;

beforeEach(function (): void {
    JudicialSyncRun::query()->delete();
});

it('reports active when a recent batch is pending', function (): void {
    JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::BatchPending,
        'started_at' => now()->subHour(),
        'data_source' => JudicialSyncDataSource::JudicialBranch,
    ]);

    expect(JudicialSyncRun::hasActiveBatch(JudicialSyncDataSource::JudicialBranch))->toBeTrue()
        ->and(JudicialSyncRun::hasActiveBatch(JudicialSyncDataSource::Samai))->toBeFalse();
});

it('reports active when a recent run is still started', function (): void {
    JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::Started,
        'started_at' => now()->subMinutes(10),
        'data_source' => JudicialSyncDataSource::Samai,
    ]);

    expect(JudicialSyncRun::hasActiveBatch(JudicialSyncDataSource::Samai))->toBeTrue()
        ->and(JudicialSyncRun::hasActiveBatch())->toBeTrue();
});

it('ignores completed and stale runs', function (): void {
    JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::BatchCompleted,
        'started_at' => now()->subHour(),
    ]);

    JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::BatchPending,
        'started_at' => now()->subHours(9),
    ]);

    expect(JudicialSyncRun::hasActiveBatch())->toBeFalse();
});

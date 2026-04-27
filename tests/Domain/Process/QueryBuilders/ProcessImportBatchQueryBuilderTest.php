<?php

declare(strict_types=1);

namespace Tests\Domain\Process\QueryBuilders;

use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessImportBatch;

it('filters batches by organization', function (): void {
    $organization = Organization::factory()->create();
    $batch = ProcessImportBatch::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $result = ProcessImportBatch::query()
        ->whereOrganization($organization->id)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($batch->id);
});

it('filters batches by status', function (): void {
    $batch = ProcessImportBatch::factory()->create([
        'status' => ProcessImportBatch::STATUS_FAILED,
    ]);

    $result = ProcessImportBatch::query()
        ->whereStatus(ProcessImportBatch::STATUS_FAILED)
        ->where('id', $batch->id)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($batch->id);
});

it('orders batches by created_at descending', function (): void {
    // We use distinct names/dates to ensure we catch the right ones
    $oldest = ProcessImportBatch::factory()->create(['created_at' => now()->subDays(10)]);
    $newest = ProcessImportBatch::factory()->create(['created_at' => now()]);

    $results = ProcessImportBatch::query()
        ->whereIn('id', [$oldest->id, $newest->id])
        ->orderedByCreatedAt()
        ->get();

    expect($results->first()->id)->toBe($newest->id);
    expect($results->last()->id)->toBe($oldest->id);
});

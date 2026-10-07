<?php

declare(strict_types=1);

namespace Tests\Domain\Process\QueryBuilders;

use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessExportStatus;
use Src\Domain\Process\Models\ProcessExport;

it('filters exports by organization', function (): void {
    $organization = Organization::factory()->create();
    $export = ProcessExport::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $result = ProcessExport::query()
        ->whereOrganization($organization->id)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($export->id);
});

it('filters exports by status', function (): void {
    $export = ProcessExport::factory()->create([
        'status' => ProcessExportStatus::Failed,
    ]);

    $result = ProcessExport::query()
        ->whereStatus(ProcessExportStatus::Failed->value)
        ->where('id', $export->id)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($export->id);
});

it('filters exports by organization name', function (): void {
    $organization = Organization::factory()->create([
        'name' => 'Unique Export Org XYZ',
    ]);
    $export = ProcessExport::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $result = ProcessExport::query()
        ->whereOrganizationNameLike('Export Org XYZ')
        ->where('id', $export->id)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($export->id);
});

it('filters exports by created_at range', function (): void {
    $export = ProcessExport::factory()->create([
        'created_at' => now()->subDays(2),
    ]);

    $result = ProcessExport::query()
        ->whereCreatedAtBetween(
            now()->subDays(3)->toDateString(),
            now()->subDay()->toDateString(),
        )
        ->where('id', $export->id)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($export->id);
});

it('orders exports by created_at descending', function (): void {
    $oldest = ProcessExport::factory()->create(['created_at' => now()->subDays(10)]);
    $newest = ProcessExport::factory()->create(['created_at' => now()]);

    $results = ProcessExport::query()
        ->whereIn('id', [$oldest->id, $newest->id])
        ->orderedByCreatedAtDesc()
        ->get();

    expect($results->first()->id)->toBe($newest->id);
    expect($results->last()->id)->toBe($oldest->id);
});

it('eager loads organization and requester details', function (): void {
    $export = ProcessExport::factory()->completed()->create();

    $result = ProcessExport::query()
        ->withDetails()
        ->whereKey($export->id)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->relationLoaded('organization'))->toBeTrue();
    expect($result->relationLoaded('requestedByUser'))->toBeTrue();
});

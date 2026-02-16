<?php

declare(strict_types=1);

use Src\Application\Admin\Organization\Data\OrganizationFilterData;
use Src\Domain\Organization\Models\Organization;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
});

it('loads app users relation when with relations is used', function (): void {
    $organization = Organization::factory()->create(['name' => 'With Users']);

    $result = Organization::query()
        ->withRelations()
        ->find($organization->id);

    expect($result)->not->toBeNull();
    expect($result->relationLoaded('appUsers'))->toBeTrue();
});

it('orders organizations by created_at descending', function (): void {
    $older = Organization::factory()->create([
        'name' => 'Older',
        'email' => 'older@qb.test',
        'created_at' => now()->subDay(),
    ]);
    $newer = Organization::factory()->create([
        'name' => 'Newer',
        'email' => 'newer@qb.test',
        'created_at' => now(),
    ]);

    $results = Organization::query()
        ->whereIn('id', [$older->id, $newer->id])
        ->orderedByCreatedAt()
        ->get();

    expect($results)->toHaveCount(2);
    expect($results->first()->id)->toBe($newer->id);
    expect($results->last()->id)->toBe($older->id);
});

it('filters organizations by name', function (): void {
    Organization::factory()->create(['name' => 'Match Name', 'email' => 'm1@qb.test']);
    Organization::factory()->create(['name' => 'Other', 'email' => 'o1@qb.test']);

    $filters = OrganizationFilterData::from(['name' => 'Match']);
    $results = Organization::query()->filters($filters)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('Match Name');
});

it('filters organizations by type', function (): void {
    $juridical = Organization::factory()->juridical()->create(['email' => 'j@qb.test']);

    $filters = OrganizationFilterData::from(['type' => 'juridical']);
    $results = Organization::query()->filters($filters)->get();

    expect($results->where('id', $juridical->id))->toHaveCount(1);
    expect($results->every(fn ($org) => $org->type === 'juridical'))->toBeTrue();
});

it('filters organizations by is_active', function (): void {
    Organization::factory()->create(['email' => 'active@qb.test', 'is_active' => true]);
    Organization::factory()->create(['email' => 'inactive@qb.test', 'is_active' => false]);

    $filters = OrganizationFilterData::from(['is_active' => 'inactive']);
    $results = Organization::query()->filters($filters)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->is_active)->toBeFalse();
});

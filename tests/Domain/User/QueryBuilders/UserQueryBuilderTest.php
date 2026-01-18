<?php

declare(strict_types=1);

use Src\Domain\User\Models\User;

it('filters users by active status', function (): void {
    $activeUser = User::factory()->create(['state' => 'active']);
    $inactiveUser = User::factory()->create(['state' => 'inactive']);

    $results = User::query()
        ->active()
        ->get();

    expect($results->pluck('id'))->toContain($activeUser->id);
    expect($results->pluck('id'))->not->toContain($inactiveUser->id);
});

it('filters users by inactive status', function (): void {
    $activeUser = User::factory()->create(['state' => 'active']);
    $inactiveUser = User::factory()->create(['state' => 'inactive']);

    $results = User::query()
        ->inactive()
        ->get();

    expect($results->pluck('id'))->toContain($inactiveUser->id);
    expect($results->pluck('id'))->not->toContain($activeUser->id);
});

it('filters users by email', function (): void {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $result = User::query()
        ->whereEmail('test@example.com')
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($user->id);
});

it('filters users by slug', function (): void {
    $user = User::factory()->create(['slug' => 'test-slug']);

    $result = User::query()
        ->whereSlug('test-slug')
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($user->id);
});

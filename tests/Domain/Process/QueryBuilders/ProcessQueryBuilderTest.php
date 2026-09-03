<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;

it('filters processes by process number', function (): void {
    $process = Process::factory()->create([
        'process_number' => '76001333301320170009301',
    ]);

    $result = Process::query()
        ->whereProcessNumber($process->process_number)
        ->find($process->id);

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($process->id);
});

it('filters processes by process id', function (): void {
    $process = Process::factory()->create([
        'process_id' => 1234567890,
    ]);

    $result = Process::query()
        ->whereProcessId(1234567890)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($process->id);
});

it('filters processes by organization', function (): void {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create();

    $process->organizations()->attach($organization->id, [
        'interest_date' => now(),
        'is_active' => true,
    ]);

    $result = Process::query()
        ->whereOrganization($organization->id)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($process->id);
});

it('orders processes by created_at', function (): void {
    $process1 = Process::factory()->create();
    $process2 = Process::factory()->create();

    $older = now()->subDays(7);
    $newer = now();
    DB::table('processes')->where('id', $process1->id)->update([
        'created_at' => $older,
        'updated_at' => $older,
    ]);
    DB::table('processes')->where('id', $process2->id)->update([
        'created_at' => $newer,
        'updated_at' => $newer,
    ]);

    $results = Process::query()
        ->whereIn('id', [$process1->id, $process2->id])
        ->orderedByCreatedAt()
        ->get();

    expect($results->first()->id)->toBe($process2->id);
    expect($results->last()->id)->toBe($process1->id);
});

it('orders processes by process_date', function (): void {
    $process1 = Process::factory()->create();
    $process2 = Process::factory()->create();

    DB::table('processes')->where('id', $process1->id)->update([
        'process_date' => now()->subDay()->toDateString(),
    ]);
    DB::table('processes')->where('id', $process2->id)->update([
        'process_date' => now()->toDateString(),
    ]);

    $results = Process::query()
        ->whereIn('id', [$process1->id, $process2->id])
        ->orderedByProcessDate()
        ->get();

    expect($results->first()->id)->toBe($process2->id);
    expect($results->last()->id)->toBe($process1->id);
});

it('includes actions relationship', function (): void {
    $process = Process::factory()->create();

    $result = Process::query()
        ->withActions()
        ->find($process->id);

    expect($result->relationLoaded('actions'))->toBeTrue();
});

it('includes subjects relationship', function (): void {
    $process = Process::factory()->create();

    $result = Process::query()
        ->withSubjects()
        ->find($process->id);

    expect($result->relationLoaded('subjects'))->toBeTrue();
});

it('includes all relationships', function (): void {
    $process = Process::factory()->create();

    $result = Process::query()
        ->withRelations()
        ->find($process->id);

    expect($result->relationLoaded('actions'))->toBeTrue();
    expect($result->relationLoaded('subjects'))->toBeTrue();
    expect($result->relationLoaded('organizations'))->toBeTrue();
});

it('excludes radicados linked only to inactive organizations from judicial daily sync', function (): void {
    $inactiveOrg = Organization::factory()->create(['is_active' => false]);
    $activeOrg = Organization::factory()->create(['is_active' => true]);

    $onlyInactive = Process::factory()->create([
        'process_number' => '76001333301320170001100',
        'process_id' => 1111111111,
        'is_manual_sync' => false,
    ]);
    $onlyInactive->organizations()->attach($inactiveOrg->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $withActive = Process::factory()->create([
        'process_number' => '76001333301320170002200',
        'process_id' => 2222222222,
        'is_manual_sync' => false,
    ]);
    $withActive->organizations()->attach($activeOrg->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $numbers = Process::query()
        ->forJudicialDailySync()
        ->pluck('process_number')
        ->all();

    expect($numbers)
        ->toContain('76001333301320170002200')
        ->not->toContain('76001333301320170001100');
});

it('includes radicado when at least one linked organization is active for daily sync', function (): void {
    $inactiveOrg = Organization::factory()->create(['is_active' => false]);
    $activeOrg = Organization::factory()->create(['is_active' => true]);

    $shared = Process::factory()->create([
        'process_number' => '76001333301320170003300',
        'process_id' => 3333333333,
        'is_manual_sync' => false,
    ]);
    $shared->organizations()->attach($inactiveOrg->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $shared->organizations()->attach($activeOrg->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $numbers = Process::query()
        ->forJudicialDailySync()
        ->pluck('process_number')
        ->all();

    expect($numbers)->toContain('76001333301320170003300');
});

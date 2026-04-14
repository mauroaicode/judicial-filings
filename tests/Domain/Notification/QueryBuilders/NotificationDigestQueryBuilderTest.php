<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\Application\AppUser\Notification\Data\NotificationDigestFilterData;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
});

it('filters by organization', function (): void {
    NotificationDigest::factory()->create(['organization_id' => $this->organization->id]);
    NotificationDigest::factory()->create();

    $results = NotificationDigest::query()->whereOrganization($this->organization->id)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->organization_id)->toBe($this->organization->id);
});

it('applies filters from data object (created_at)', function (): void {
    NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'created_at' => '2026-03-10 10:00:00',
    ]);
    NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'created_at' => '2026-03-20 10:00:00',
    ]);

    $filters = NotificationDigestFilterData::from([
        'created_at_from' => '2026-03-15',
        'created_at_to' => '2026-03-25',
    ]);

    $results = NotificationDigest::query()->filters($filters)->get();

    expect($results)->toHaveCount(1);
});

it('applies action criteria filters (process_number)', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, ['interest_date' => now()]);

    $action = ProcessAction::factory()->create(['process_id' => $process->id]);
    $digest = NotificationDigest::factory()->create(['organization_id' => $this->organization->id]);

    DB::table('organization_notifications')->insert([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notification_digest_id' => $digest->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => ProcessAction::class,
        'notification_type' => 'actuacion',
        'is_viewed' => false,
        'is_notified' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $filters = NotificationDigestFilterData::from(['process_number' => $process->process_number]);

    $results = NotificationDigest::query()->filters($filters)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($digest->id);
});

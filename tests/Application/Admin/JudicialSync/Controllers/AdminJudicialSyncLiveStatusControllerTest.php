<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;
use Src\Domain\JudicialSync\Events\JudicialSyncStatusUpdated;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    JudicialSyncRun::query()->delete();

    $this->user = User::factory()->create([
        'email' => 'admin@judicial-sync-live.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);
});

it('requires authentication for judicial sync live status', function (): void {
    $this->getJson('/api/admin/judicial-sync/status')->assertStatus(401);
});

it('forbids non-admin users from judicial sync live status', function (): void {
    $plainUser = User::factory()->create([
        'email' => 'plain@judicial-sync-live.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $this->actingAs($plainUser)->getJson('/api/admin/judicial-sync/status')->assertStatus(403);
});

it('returns idle sources when no sync is running', function (): void {
    $response = $this->actingAs($this->user)->getJson('/api/admin/judicial-sync/status');

    $response->assertStatus(200);
    $response->assertJsonPath('has_active', false);
    $response->assertJsonPath('active_count', 0);
    $response->assertJsonCount(3, 'sources');
    $response->assertJsonPath('sources.0.data_source', JudicialSyncDataSource::JudicialBranch->value);
    $response->assertJsonPath('sources.0.is_active', false);
    $response->assertJsonPath('sources.1.data_source', JudicialSyncDataSource::Samai->value);
    $response->assertJsonPath('sources.2.data_source', JudicialSyncDataSource::Tyba->value);
    $response->assertJsonPath('active', []);
});

it('returns active rama and samai runs concurrently for the header', function (): void {
    Event::fake([JudicialSyncStatusUpdated::class]);

    $rama = JudicialSyncRun::factory()->create([
        'data_source' => JudicialSyncDataSource::JudicialBranch,
        'status' => JudicialSyncRunStatus::BatchPending,
        'processes_queued' => 100,
        'started_at' => now()->subMinutes(10),
        'batch_finished_at' => null,
        'laravel_batch_id' => null,
    ]);

    $samai = JudicialSyncRun::factory()->create([
        'data_source' => JudicialSyncDataSource::Samai,
        'status' => JudicialSyncRunStatus::BatchPending,
        'processes_queued' => 28,
        'started_at' => now()->subMinutes(5),
        'batch_finished_at' => null,
        'laravel_batch_id' => null,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/admin/judicial-sync/status');

    $response->assertStatus(200);
    $response->assertJsonPath('has_active', true);
    $response->assertJsonPath('active_count', 2);
    $response->assertJsonCount(2, 'active');

    $activeSources = collect($response->json('active'))->pluck('data_source')->all();
    expect($activeSources)->toContain(JudicialSyncDataSource::JudicialBranch->value)
        ->and($activeSources)->toContain(JudicialSyncDataSource::Samai->value);

    $ramaRow = collect($response->json('sources'))->firstWhere('data_source', JudicialSyncDataSource::JudicialBranch->value);
    expect($ramaRow['is_active'])->toBeTrue()
        ->and($ramaRow['run_id'])->toBe($rama->id)
        ->and($ramaRow['processes_queued'])->toBe(100);

    $samaiRow = collect($response->json('sources'))->firstWhere('data_source', JudicialSyncDataSource::Samai->value);
    expect($samaiRow['is_active'])->toBeTrue()
        ->and($samaiRow['run_id'])->toBe($samai->id)
        ->and($samaiRow['processes_queued'])->toBe(28);
});

it('keeps the global rama chip and adds the organization run to the header', function (): void {
    $organization = Organization::factory()->create(['name' => 'Organización Beta']);
    $batchId = 'org-batch-'.uniqid();

    DB::table('job_batches')->insert([
        'id' => $batchId,
        'name' => 'Organization sync batch',
        'total_jobs' => 12,
        'pending_jobs' => 7,
        'failed_jobs' => 1,
        'failed_job_ids' => '[]',
        'options' => null,
        'cancelled_at' => null,
        'created_at' => time(),
        'finished_at' => null,
    ]);

    $global = JudicialSyncRun::factory()->create([
        'data_source' => JudicialSyncDataSource::JudicialBranch,
        'status' => JudicialSyncRunStatus::BatchPending,
        'processes_queued' => 100,
        'organization_id' => null,
        'started_at' => now()->subMinutes(10),
        'batch_finished_at' => null,
    ]);

    $scoped = JudicialSyncRun::factory()->create([
        'data_source' => JudicialSyncDataSource::JudicialBranch,
        'status' => JudicialSyncRunStatus::BatchPending,
        'processes_queued' => 12,
        'organization_id' => $organization->id,
        'laravel_batch_id' => $batchId,
        'started_at' => now()->subMinutes(2),
        'batch_finished_at' => null,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/admin/judicial-sync/status');

    $response->assertStatus(200);
    $response->assertJsonPath('has_active', true);
    $response->assertJsonPath('active_count', 2);
    $response->assertJsonCount(3, 'sources');

    $ramaRow = collect($response->json('sources'))->firstWhere('data_source', JudicialSyncDataSource::JudicialBranch->value);
    expect($ramaRow['run_id'])->toBe($global->id)
        ->and($ramaRow['organization_id'])->toBeNull()
        ->and($ramaRow['organization_name'])->toBeNull();

    $organizationRow = collect($response->json('active'))->firstWhere('run_id', $scoped->id);
    expect($organizationRow)->not->toBeNull()
        ->and($organizationRow['organization_id'])->toBe($organization->id)
        ->and($organizationRow['organization_name'])->toBe('Organización Beta')
        ->and($organizationRow['processes_queued'])->toBe(12)
        ->and($organizationRow['queue_pending_jobs'])->toBe(7);
});

it('hides aborted organization runs that never queued a batch', function (): void {
    $organization = Organization::factory()->create(['name' => 'Organización Detenida']);

    $running = JudicialSyncRun::factory()->create([
        'data_source' => JudicialSyncDataSource::JudicialBranch,
        'status' => JudicialSyncRunStatus::BatchPending,
        'processes_queued' => 40,
        'organization_id' => $organization->id,
        'started_at' => now()->subMinutes(5),
        'batch_finished_at' => null,
    ]);

    JudicialSyncRun::factory()->create([
        'data_source' => JudicialSyncDataSource::JudicialBranch,
        'status' => JudicialSyncRunStatus::Started,
        'processes_queued' => 0,
        'organization_id' => $organization->id,
        'laravel_batch_id' => null,
        'started_at' => now()->subMinutes(10),
        'batch_finished_at' => null,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/admin/judicial-sync/status');

    $response->assertStatus(200);
    $response->assertJsonPath('active_count', 1);
    $response->assertJsonPath('active.0.run_id', $running->id);
    $response->assertJsonPath('active.0.processes_queued', 40);
});

it('ignores finished runs in live status', function (): void {
    JudicialSyncRun::factory()->create([
        'data_source' => JudicialSyncDataSource::JudicialBranch,
        'status' => JudicialSyncRunStatus::BatchCompleted,
        'processes_queued' => 50,
        'started_at' => now()->subMinutes(30),
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/admin/judicial-sync/status');

    $response->assertStatus(200);
    $response->assertJsonPath('has_active', false);
    $response->assertJsonPath('active_count', 0);
});

it('broadcasts judicial sync status when a run starts', function (): void {
    Event::fake([JudicialSyncStatusUpdated::class]);

    $run = JudicialSyncRun::startRun(null, JudicialSyncDataSource::JudicialBranch);

    Event::assertDispatched(
        JudicialSyncStatusUpdated::class,
        fn (JudicialSyncStatusUpdated $event): bool => $event->runId === $run->id
            && $event->isActive === true
            && $event->dataSource === JudicialSyncDataSource::JudicialBranch
            && $event->organizationId === null
            && $event->organizationName === null
            && $event->broadcastAs() === 'JudicialSyncStatusUpdated'
            && $event->broadcastWith()['organization_id'] === null,
    );
});

it('broadcasts the organization name when a scoped run starts', function (): void {
    Event::fake([JudicialSyncStatusUpdated::class]);

    $organization = Organization::factory()->create(['name' => 'Organización Gamma']);
    $run = JudicialSyncRun::startRun(null, JudicialSyncDataSource::JudicialBranch, $organization->id);

    Event::assertDispatched(
        JudicialSyncStatusUpdated::class,
        fn (JudicialSyncStatusUpdated $event): bool => $event->runId === $run->id
            && $event->organizationId === $organization->id
            && $event->organizationName === 'Organización Gamma'
            && $event->broadcastWith()['organization_name'] === 'Organización Gamma',
    );
});

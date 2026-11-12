<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Src\Application\Admin\JudicialSync\Jobs\QueueOrganizationJudicialSyncJob;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    JudicialSyncRun::query()->delete();

    $this->user = User::factory()->create([
        'email' => 'admin@organization-judicial-sync.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);
});

it('requires authentication to sync one organization', function (): void {
    $organization = Organization::factory()->create();

    $this->postJson('/api/admin/judicial-sync/organizations/'.$organization->id)
        ->assertStatus(401);
});

it('forbids non-admin users from syncing one organization', function (): void {
    $organization = Organization::factory()->create();
    $plainUser = User::factory()->create([
        'email' => 'plain@organization-judicial-sync.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $this->actingAs($plainUser)
        ->postJson('/api/admin/judicial-sync/organizations/'.$organization->id)
        ->assertStatus(403);
});

it('returns not found when the organization does not exist', function (): void {
    $this->actingAs($this->user)
        ->postJson('/api/admin/judicial-sync/organizations/'.Str::uuid())
        ->assertStatus(404);
});

it('rejects an organization that cannot track processes', function (): void {
    $organization = Organization::factory()->create([
        'is_process_tracking_enabled' => false,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/admin/judicial-sync/organizations/'.$organization->id)
        ->assertStatus(422);
});

it('validates radicado has 23 digits when provided', function (): void {
    $organization = Organization::factory()->create();

    $this->actingAs($this->user)
        ->postJson('/api/admin/judicial-sync/organizations/'.$organization->id, [
            'radicado' => '123',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['radicado']);
});

it('returns no batch when the organization has no radicados', function (): void {
    Bus::fake();

    $organization = Organization::factory()->create(['name' => 'Sin procesos']);

    $this->actingAs($this->user)
        ->postJson('/api/admin/judicial-sync/organizations/'.$organization->id)
        ->assertStatus(200)
        ->assertJsonPath('jobs_dispatched', 0)
        ->assertJsonPath('batch_dispatched', false)
        ->assertJsonPath('organization_id', $organization->id)
        ->assertJsonPath('organization_name', 'Sin procesos')
        ->assertJsonPath('data_source', JudicialSyncDataSource::JudicialBranch->value);

    Bus::assertNothingBatched();
});

it('dispatches only the radicados of that organization, including shared ones', function (): void {
    Bus::fake();

    $organizationA = Organization::factory()->create(['name' => 'Organización Alfa']);
    $organizationB = Organization::factory()->create();
    $pivot = [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ];

    $shared = Process::factory()->create([
        'process_number' => '76001333301320170025500',
        'is_manual_sync' => false,
    ]);
    $onlyA = Process::factory()->create([
        'process_number' => '76001333301320170026600',
        'is_manual_sync' => false,
    ]);
    $onlyB = Process::factory()->create([
        'process_number' => '76001333301320170027700',
        'is_manual_sync' => false,
    ]);

    $shared->organizations()->attach([$organizationA->id => $pivot, $organizationB->id => $pivot]);
    $onlyA->organizations()->attach($organizationA->id, $pivot);
    $onlyB->organizations()->attach($organizationB->id, $pivot);

    $this->actingAs($this->user)
        ->postJson('/api/admin/judicial-sync/organizations/'.$organizationA->id)
        ->assertStatus(200)
        ->assertJsonPath('jobs_dispatched', 2)
        ->assertJsonPath('batch_dispatched', true)
        ->assertJsonPath('organization_id', $organizationA->id)
        ->assertJsonPath('organization_name', 'Organización Alfa')
        ->assertJsonPath('data_source', JudicialSyncDataSource::JudicialBranch->value)
        ->assertJsonPath('radicado_filter', null);

    Bus::assertDispatched(
        QueueOrganizationJudicialSyncJob::class,
        fn (QueueOrganizationJudicialSyncJob $job): bool => $job->organizationId === $organizationA->id
            && $job->radicadoFilter === null,
    );
});

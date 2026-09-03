<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessTimelineEvent;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin-process-trash@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create(['name' => 'Org Trash A', 'is_active' => true]);
    $this->otherOrganization = Organization::factory()->create(['name' => 'Org Trash B', 'is_active' => true]);
});

function attachProcessToOrgForTrashTest(Process $process, Organization $organization, OrganizationProcessStatus $status = OrganizationProcessStatus::ACTIVE): void
{
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => $status->toIsActive(),
        'status' => $status->value,
    ]);
}

it('requires authentication to trash processes', function (): void {
    $this->deleteJson('/api/admin/processes', [
        'organization_id' => $this->organization->id,
        'process_ids' => ['00000000-0000-0000-0000-000000000001'],
    ])->assertUnauthorized();
});

it('moves a single process to trash for one organization from detail', function (): void {
    $process = Process::factory()->create();
    attachProcessToOrgForTrashTest($process, $this->organization);
    attachProcessToOrgForTrashTest($process, $this->otherOrganization);

    $this->actingAs($this->user)
        ->deleteJson("/api/admin/processes/{$process->id}", [
            'organization_id' => $this->organization->id,
        ])
        ->assertOk()
        ->assertJsonPath('trashed_count', 1)
        ->assertJsonPath('trashed_ids.0', $process->id);

    expect(
        OrganizationProcess::query()
            ->where('organization_id', $this->organization->id)
            ->where('process_id', $process->id)
            ->exists()
    )->toBeFalse()
        ->and(
            OrganizationProcess::onlyTrashed()
                ->where('organization_id', $this->organization->id)
                ->where('process_id', $process->id)
                ->exists()
        )->toBeTrue()
        ->and(
            OrganizationProcess::query()
                ->where('organization_id', $this->otherOrganization->id)
                ->where('process_id', $process->id)
                ->exists()
        )->toBeTrue();

    $trashed = OrganizationProcess::onlyTrashed()
        ->where('organization_id', $this->organization->id)
        ->where('process_id', $process->id)
        ->first();

    expect($trashed->is_active)->toBeFalse()
        ->and($trashed->status)->toBe(OrganizationProcessStatus::INACTIVE)
        ->and($trashed->deleted_by)->toBe($this->user->id);

    expect(
        ProcessTimelineEvent::query()
            ->where('process_id', $process->id)
            ->where('event_type', ProcessTimelineEventType::TRACKING_TRASHED->value)
            ->exists()
    )->toBeTrue();
});

it('bulk-trashes selected processes for an organization', function (): void {
    $keep = Process::factory()->create();
    $first = Process::factory()->create();
    $second = Process::factory()->create();

    attachProcessToOrgForTrashTest($keep, $this->organization);
    attachProcessToOrgForTrashTest($first, $this->organization);
    attachProcessToOrgForTrashTest($second, $this->organization);

    $this->actingAs($this->user)
        ->deleteJson('/api/admin/processes', [
            'organization_id' => $this->organization->id,
            'process_ids' => [$first->id, $second->id],
        ])
        ->assertOk()
        ->assertJsonPath('trashed_count', 2);

    expect(
        OrganizationProcess::query()
            ->where('organization_id', $this->organization->id)
            ->where('process_id', $keep->id)
            ->exists()
    )->toBeTrue()
        ->and(
            OrganizationProcess::query()
                ->where('organization_id', $this->organization->id)
                ->whereIn('process_id', [$first->id, $second->id])
                ->count()
        )->toBe(0)
        ->and(
            OrganizationProcess::onlyTrashed()
                ->where('organization_id', $this->organization->id)
                ->whereIn('process_id', [$first->id, $second->id])
                ->count()
        )->toBe(2);
});

it('skips already trashed or unlinked processes in bulk without aborting the rest', function (): void {
    $alive = Process::factory()->create();
    $alreadyTrashed = Process::factory()->create();
    $unlinked = Process::factory()->create();

    attachProcessToOrgForTrashTest($alive, $this->organization);
    attachProcessToOrgForTrashTest($alreadyTrashed, $this->organization);

    OrganizationProcess::query()
        ->where('organization_id', $this->organization->id)
        ->where('process_id', $alreadyTrashed->id)
        ->first()
        ?->delete();

    $response = $this->actingAs($this->user)
        ->deleteJson('/api/admin/processes', [
            'organization_id' => $this->organization->id,
            'process_ids' => [$alive->id, $alreadyTrashed->id, $unlinked->id],
        ]);

    $response->assertOk()
        ->assertJsonPath('trashed_count', 1)
        ->assertJsonPath('trashed_ids.0', $alive->id);

    $skippedReasons = collect($response->json('skipped'))->pluck('reason', 'process_id');

    expect($skippedReasons[$alreadyTrashed->id])->toBe('already_trashed')
        ->and($skippedReasons[$unlinked->id])->toBe('not_linked');
});

it('returns 404 when trashing a process that is not linked to the organization', function (): void {
    $process = Process::factory()->create();

    $this->actingAs($this->user)
        ->deleteJson("/api/admin/processes/{$process->id}", [
            'organization_id' => $this->organization->id,
        ])
        ->assertNotFound();
});

it('hides trashed processes from the app-user list and quota for that organization', function (): void {
    $appUser = AppUser::factory()->create();
    $appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);

    $process = Process::factory()->create();
    attachProcessToOrgForTrashTest($process, $this->organization);

    $this->actingAs($this->user)
        ->deleteJson("/api/admin/processes/{$process->id}", [
            'organization_id' => $this->organization->id,
        ])
        ->assertOk();

    $this->actingAs($appUser)
        ->getJson('/api/app-user/processes')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($appUser)
        ->getJson('/api/app-user/process-quota')
        ->assertOk()
        ->assertJsonPath('active_processes_count', 0);
});

it('keeps the process visible for other organizations after trash', function (): void {
    $appUser = AppUser::factory()->create();
    $appUser->organizations()->attach($this->otherOrganization->id, ['is_owner' => true]);

    $process = Process::factory()->create();
    attachProcessToOrgForTrashTest($process, $this->organization);
    attachProcessToOrgForTrashTest($process, $this->otherOrganization);

    $this->actingAs($this->user)
        ->deleteJson("/api/admin/processes/{$process->id}", [
            'organization_id' => $this->organization->id,
        ])
        ->assertOk();

    $listIds = collect(
        $this->actingAs($appUser)
            ->getJson('/api/app-user/processes')
            ->assertOk()
            ->json('data')
    )->pluck('id')->all();

    expect($listIds)->toContain($process->id);
});

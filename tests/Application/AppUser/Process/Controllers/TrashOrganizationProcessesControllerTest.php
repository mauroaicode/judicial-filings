<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Organization\Models\OrganizationSetting;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessTimelineEvent;

beforeEach(function (): void {
    config(['organization.defaults.max_active_processes' => 10]);

    $this->organization = Organization::factory()->create([
        'name' => 'AppUser Trash Org '.Str::uuid(),
        'is_active' => true,
    ]);

    OrganizationSetting::factory()->create([
        'organization_id' => $this->organization->id,
        'max_active_processes' => 5,
    ]);

    $this->appUser = AppUser::factory()->create([
        'email' => 'appuser-trash-'.Str::uuid().'@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);

    $this->appUser->organizations()->attach($this->organization->id, [
        'is_owner' => true,
    ]);
});

function attachActiveProcessForAppUserTrash(Process $process, Organization $organization): void
{
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => OrganizationProcessStatus::ACTIVE->value,
    ]);
}

it('requires authentication to trash processes', function (): void {
    $this->deleteJson('/api/app-user/processes', [
        'process_ids' => ['00000000-0000-0000-0000-000000000001'],
    ])->assertUnauthorized();
});

it('trashes a single process and frees a cupo slot', function (): void {
    $keep = Process::factory()->create(['process_number' => '11001400300120240000001']);
    $trash = Process::factory()->create(['process_number' => '11001400300120240000002']);

    attachActiveProcessForAppUserTrash($keep, $this->organization);
    attachActiveProcessForAppUserTrash($trash, $this->organization);

    $this->actingAs($this->appUser)
        ->getJson('/api/app-user/process-quota')
        ->assertOk()
        ->assertJsonPath('active_processes_count', 2)
        ->assertJsonPath('remaining_slots', 3);

    $response = $this->actingAs($this->appUser)
        ->deleteJson("/api/app-user/processes/{$trash->id}")
        ->assertOk()
        ->assertJsonPath('trashed_count', 1)
        ->assertJsonPath('trashed_ids.0', $trash->id)
        ->assertJsonPath('quota.active_processes_count', 1)
        ->assertJsonPath('quota.remaining_slots', 4)
        ->assertJsonPath('quota.max_active_processes', 5);

    expect(
        OrganizationProcess::query()
            ->where('organization_id', $this->organization->id)
            ->where('process_id', $trash->id)
            ->exists()
    )->toBeFalse()
        ->and(
            OrganizationProcess::onlyTrashed()
                ->where('organization_id', $this->organization->id)
                ->where('process_id', $trash->id)
                ->value('deleted_by')
        )->toBe($this->appUser->id)
        ->and(
            OrganizationProcess::query()
                ->where('organization_id', $this->organization->id)
                ->where('process_id', $keep->id)
                ->exists()
        )->toBeTrue();

    expect(
        ProcessTimelineEvent::query()
            ->where('process_id', $trash->id)
            ->where('event_type', ProcessTimelineEventType::TRACKING_TRASHED->value)
            ->where('actor_type', 'app_user')
            ->exists()
    )->toBeTrue();

    expect($response->json('message'))->not->toBeEmpty();
});

it('bulk-trashes selected processes', function (): void {
    $keep = Process::factory()->create(['process_number' => '11001400300120240000010']);
    $first = Process::factory()->create(['process_number' => '11001400300120240000011']);
    $second = Process::factory()->create(['process_number' => '11001400300120240000012']);

    attachActiveProcessForAppUserTrash($keep, $this->organization);
    attachActiveProcessForAppUserTrash($first, $this->organization);
    attachActiveProcessForAppUserTrash($second, $this->organization);

    $this->actingAs($this->appUser)
        ->deleteJson('/api/app-user/processes', [
            'process_ids' => [$first->id, $second->id],
        ])
        ->assertOk()
        ->assertJsonPath('trashed_count', 2)
        ->assertJsonPath('quota.active_processes_count', 1)
        ->assertJsonPath('quota.remaining_slots', 4);

    expect(
        OrganizationProcess::query()
            ->where('organization_id', $this->organization->id)
            ->count()
    )->toBe(1);
});

it('trashes all organization processes when all=true', function (): void {
    $first = Process::factory()->create(['process_number' => '11001400300120240000021']);
    $second = Process::factory()->create(['process_number' => '11001400300120240000022']);

    attachActiveProcessForAppUserTrash($first, $this->organization);
    attachActiveProcessForAppUserTrash($second, $this->organization);

    $this->actingAs($this->appUser)
        ->deleteJson('/api/app-user/processes', ['all' => true])
        ->assertOk()
        ->assertJsonPath('trashed_count', 2)
        ->assertJsonPath('quota.active_processes_count', 0)
        ->assertJsonPath('quota.remaining_slots', 5)
        ->assertJsonPath('quota.can_add_process', true);

    expect(
        OrganizationProcess::query()
            ->where('organization_id', $this->organization->id)
            ->count()
    )->toBe(0)
        ->and(
            OrganizationProcess::onlyTrashed()
                ->where('organization_id', $this->organization->id)
                ->count()
        )->toBe(2);
});

it('expands trash to all instances of the same radicado so the cupo is freed', function (): void {
    $radicado = '11001400300120240000033';
    $instanceA = Process::factory()->create(['process_number' => $radicado]);
    $instanceB = Process::factory()->create(['process_number' => $radicado]);

    attachActiveProcessForAppUserTrash($instanceA, $this->organization);
    attachActiveProcessForAppUserTrash($instanceB, $this->organization);

    $this->actingAs($this->appUser)
        ->getJson('/api/app-user/process-quota')
        ->assertOk()
        ->assertJsonPath('active_processes_count', 1);

    $this->actingAs($this->appUser)
        ->deleteJson("/api/app-user/processes/{$instanceA->id}")
        ->assertOk()
        ->assertJsonPath('trashed_count', 2)
        ->assertJsonPath('quota.active_processes_count', 0)
        ->assertJsonPath('quota.remaining_slots', 5);

    expect(
        OrganizationProcess::query()
            ->where('organization_id', $this->organization->id)
            ->whereIn('process_id', [$instanceA->id, $instanceB->id])
            ->count()
    )->toBe(0);
});

it('returns 404 when trashing a process not linked to the organization', function (): void {
    $otherOrg = Organization::factory()->create(['is_active' => true]);
    $process = Process::factory()->create();
    attachActiveProcessForAppUserTrash($process, $otherOrg);

    $this->actingAs($this->appUser)
        ->deleteJson("/api/app-user/processes/{$process->id}")
        ->assertNotFound();
});

it('does not trash processes belonging to another organization when using all', function (): void {
    $otherOrg = Organization::factory()->create(['is_active' => true]);
    $mine = Process::factory()->create(['process_number' => '11001400300120240000041']);
    $theirs = Process::factory()->create(['process_number' => '11001400300120240000042']);

    attachActiveProcessForAppUserTrash($mine, $this->organization);
    attachActiveProcessForAppUserTrash($theirs, $otherOrg);

    $this->actingAs($this->appUser)
        ->deleteJson('/api/app-user/processes', ['all' => true])
        ->assertOk()
        ->assertJsonPath('trashed_count', 1);

    expect(
        OrganizationProcess::query()
            ->where('organization_id', $otherOrg->id)
            ->where('process_id', $theirs->id)
            ->exists()
    )->toBeTrue();
});

it('validates bulk payload requires process_ids or all', function (): void {
    $this->actingAs($this->appUser)
        ->deleteJson('/api/app-user/processes', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['process_ids']);
});

it('rejects process_ids when all=true', function (): void {
    $process = Process::factory()->create();
    attachActiveProcessForAppUserTrash($process, $this->organization);

    $this->actingAs($this->appUser)
        ->deleteJson('/api/app-user/processes', [
            'all' => true,
            'process_ids' => [$process->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['process_ids']);
});

it('hides trashed processes from the app-user list', function (): void {
    $process = Process::factory()->create();
    attachActiveProcessForAppUserTrash($process, $this->organization);

    $this->actingAs($this->appUser)
        ->deleteJson("/api/app-user/processes/{$process->id}")
        ->assertOk();

    $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

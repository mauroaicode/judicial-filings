<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Src\Application\Shared\Jobs\SendOrganizationDigestJob;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    OrganizationNotification::query()->delete();
    NotificationDigest::query()->delete();

    $this->user = User::factory()->create([
        'email' => 'admin-digest@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);
    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->orgA = Organization::factory()->create(['name' => 'Bufete Alpha']);
    $this->orgB = Organization::factory()->create(['name' => 'Bufete Beta']);
});

function attachActiveProcess(Organization $org, ?Process $process = null): Process
{
    $process ??= Process::factory()->create();
    $process->organizations()->attach($org->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus::ACTIVE->value,
    ]);

    return $process;
}

function createPendingActuacionNotification(Organization $org, ProcessAction $action): void
{
    OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $org->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
        'is_notified' => false,
        'is_email_notified' => false,
    ]);
}

// ─── Auth ──────────────────────────────────────────────────────────────────

it('requires authentication for preview', function (): void {
    $this->getJson('/api/admin/digest-packages/preview')->assertStatus(401);
});

it('requires authentication for send', function (): void {
    $this->postJson('/api/admin/digest-packages/send')->assertStatus(401);
});

it('forbids non-admin users on preview', function (): void {
    $plain = User::factory()->create([
        'email' => 'plain-digest@example.com',
        'password' => Hash::make('p'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $this->actingAs($plain)->getJson('/api/admin/digest-packages/preview')->assertStatus(403);
});

it('forbids non-admin users on send', function (): void {
    $plain = User::factory()->create([
        'email' => 'plain-digest2@example.com',
        'password' => Hash::make('p'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $this->actingAs($plain)->postJson('/api/admin/digest-packages/send')->assertStatus(403);
});

// ─── Preview ───────────────────────────────────────────────────────────────

it('returns empty preview when no pending notifications exist', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/digest-packages/preview');

    $response->assertStatus(200);
    expect($response->json('consolidates_ready'))->toBe(0)
        ->and($response->json('total_pending_processes'))->toBe(0)
        ->and($response->json('total_pending_actions'))->toBe(0)
        ->and($response->json('organizations'))->toBe([]);
});

it('lists consolidates with pending processes and actions', function (): void {
    $process1 = attachActiveProcess($this->orgA);
    $process2 = attachActiveProcess($this->orgA);

    $action1 = ProcessAction::factory()->create([
        'process_id' => $process1->id,
        'registration_date' => now()->toDateString(),
    ]);
    $action2 = ProcessAction::factory()->create([
        'process_id' => $process1->id,
        'registration_date' => now()->toDateString(),
    ]);
    $action3 = ProcessAction::factory()->create([
        'process_id' => $process2->id,
        'registration_date' => now()->toDateString(),
    ]);

    createPendingActuacionNotification($this->orgA, $action1);
    createPendingActuacionNotification($this->orgA, $action2);
    createPendingActuacionNotification($this->orgA, $action3);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/digest-packages/preview');

    $response->assertStatus(200);
    expect($response->json('consolidates_ready'))->toBe(1)
        ->and($response->json('total_pending_processes'))->toBe(2)
        ->and($response->json('total_pending_actions'))->toBe(3);

    $orgs = $response->json('organizations');
    expect($orgs)->toHaveCount(1)
        ->and($orgs[0]['organization_id'])->toBe($this->orgA->id)
        ->and($orgs[0]['organization_name'])->toBe('Bufete Alpha')
        ->and($orgs[0]['pending_processes'])->toBe(2)
        ->and($orgs[0]['pending_actions'])->toBe(3);
});

it('ignores non-ProcessAction pending notifications in preview', function (): void {
    OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $this->orgA->id,
        'notifiable_id' => $this->orgA->id,
        'notifiable_type' => Process::class,
        'notification_type' => 'proceso',
        'is_viewed' => false,
        'is_notified' => false,
        'is_email_notified' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/digest-packages/preview');

    $response->assertStatus(200);
    expect($response->json('consolidates_ready'))->toBe(0)
        ->and($response->json('organizations'))->toBe([]);
});

it('excludes actuaciones blocked by registration cutoff from preview', function (): void {
    $process = attachActiveProcess($this->orgA);

    $oldAction = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => '2026-07-01',
        'created_at' => now()->subDays(10),
    ]);
    $sentAction = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => '2026-07-20',
        'created_at' => now()->subDays(5),
    ]);

    $digest = NotificationDigest::query()->create([
        'organization_id' => $this->orgA->id,
        'data' => [],
    ]);

    OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $this->orgA->id,
        'notifiable_id' => $sentAction->id,
        'notifiable_type' => $sentAction->getMorphClass(),
        'notification_type' => 'actuacion',
        'notification_digest_id' => $digest->id,
        'is_viewed' => true,
        'is_notified' => true,
        'is_email_notified' => true,
    ]);

    createPendingActuacionNotification($this->orgA, $oldAction);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/digest-packages/preview');

    $response->assertStatus(200);
    expect($response->json('consolidates_ready'))->toBe(0)
        ->and($response->json('organizations'))->toBe([]);
});

it('includes active notification channels per organization in preview', function (): void {
    $this->orgA->notificationChannels()->create([
        'channel_type' => 'email',
        'channel_value' => 'first@bufete.com',
        'is_active' => true,
        'priority' => 1,
    ]);
    $this->orgA->notificationChannels()->create([
        'channel_type' => 'email',
        'channel_value' => 'second@bufete.com',
        'is_active' => true,
        'priority' => 2,
    ]);
    $this->orgA->notificationChannels()->create([
        'channel_type' => 'email',
        'channel_value' => 'inactive@bufete.com',
        'is_active' => false,
        'priority' => 3,
    ]);

    $process = attachActiveProcess($this->orgA);
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => now()->toDateString(),
    ]);
    createPendingActuacionNotification($this->orgA, $action);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/digest-packages/preview');

    $response->assertStatus(200);
    $channels = $response->json('organizations.0.channels');
    expect($channels)->toHaveKey('email')
        ->and($channels['email'])->toContain('first@bufete.com', 'second@bufete.com')
        ->and($channels['email'])->not->toContain('inactive@bufete.com');
});

it('preview exposes auto_digest_enabled flag reflecting config', function (): void {
    config(['judicial-sync.auto_digest_after_sync' => false]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/digest-packages/preview');

    $response->assertStatus(200);
    expect($response->json('auto_digest_enabled'))->toBeFalse();

    config(['judicial-sync.auto_digest_after_sync' => true]);
});

// ─── Send ──────────────────────────────────────────────────────────────────

it('returns nothing pending when no unnotified notifications exist on send', function (): void {
    Queue::fake();

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/digest-packages/send');

    $response->assertStatus(200);
    expect($response->json('organizations_queued'))->toBe(0);
    Queue::assertNothingPushed();
});

it('dispatches SendOrganizationDigestJob for each org with eligible pending actuaciones', function (): void {
    Queue::fake();

    $processA = attachActiveProcess($this->orgA);
    foreach (range(1, 2) as $_) {
        $action = ProcessAction::factory()->create([
            'process_id' => $processA->id,
            'registration_date' => now()->toDateString(),
        ]);
        createPendingActuacionNotification($this->orgA, $action);
    }

    $processB = attachActiveProcess($this->orgB);
    $actionB = ProcessAction::factory()->create([
        'process_id' => $processB->id,
        'registration_date' => now()->toDateString(),
    ]);
    createPendingActuacionNotification($this->orgB, $actionB);

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/digest-packages/send');

    $response->assertStatus(200);
    expect($response->json('organizations_queued'))->toBe(2);

    Queue::assertPushed(SendOrganizationDigestJob::class, 2);
});

it('does not dispatch jobs when only non-ProcessAction notifications are pending', function (): void {
    Queue::fake();

    OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $this->orgA->id,
        'notifiable_id' => $this->orgA->id,
        'notifiable_type' => Process::class,
        'notification_type' => 'proceso',
        'is_viewed' => false,
        'is_notified' => false,
        'is_email_notified' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/digest-packages/send');

    $response->assertStatus(200);
    expect($response->json('organizations_queued'))->toBe(0);
    Queue::assertNothingPushed();
});

it('does not dispatch jobs for already notified organizations', function (): void {
    Queue::fake();

    $process = attachActiveProcess($this->orgA);
    $action = ProcessAction::factory()->create(['process_id' => $process->id]);

    OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $this->orgA->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => true,
        'is_notified' => true,
        'is_email_notified' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/digest-packages/send');

    $response->assertStatus(200);
    expect($response->json('organizations_queued'))->toBe(0);
    Queue::assertNothingPushed();
});

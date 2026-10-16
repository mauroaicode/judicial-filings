<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Src\Application\Shared\Notifications\OrganizationDeactivatedNotification;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Enums\OrganizationStatusMode;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Models\Process;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    Notification::fake();

    $this->user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create([
        'is_active' => true,
        'is_process_tracking_enabled' => true,
        'name' => 'Bufete Demo',
    ]);

    $this->organization->notificationChannels()->createMany([
        ['channel_type' => 'email', 'channel_value' => 'test@test.com', 'is_active' => true, 'priority' => 1],
        ['channel_type' => 'whatsapp', 'channel_value' => '+573001234567', 'is_active' => true, 'priority' => 1],
        ['channel_type' => 'internal', 'channel_value' => 'internal', 'is_active' => true, 'priority' => 1],
    ]);

    $this->owner = AppUser::factory()->create([
        'email' => 'owner@example.com',
        'name' => 'Ana',
        'last_name' => 'Pérez',
    ]);
    $this->owner->organizations()->attach($this->organization->id, ['is_owner' => true]);

    $this->member = AppUser::factory()->create([
        'email' => 'member@example.com',
        'name' => 'Luis',
        'last_name' => 'Gómez',
    ]);
    $this->member->organizations()->attach($this->organization->id, ['is_owner' => false]);
});

it('fully deactivates an organization (no login + no tracking + channels off)', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson("/api/admin/organizations/{$this->organization->id}/active-status", [
            'mode' => 'inactive',
        ]);

    $response->assertStatus(204);

    $organization = $this->organization->fresh();

    expect($organization->is_active)->toBeFalse()
        ->and($organization->is_process_tracking_enabled)->toBeFalse()
        ->and($organization->statusMode())->toBe(OrganizationStatusMode::Inactive)
        ->and($this->organization->notificationChannels()->where('is_active', true)->count())->toBe(0);

    Notification::assertNothingSent();
});

it('pauses process tracking while keeping login access', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson("/api/admin/organizations/{$this->organization->id}/active-status", [
            'mode' => 'tracking_paused',
        ]);

    $response->assertStatus(204);

    $organization = $this->organization->fresh();

    expect($organization->is_active)->toBeTrue()
        ->and($organization->is_process_tracking_enabled)->toBeFalse()
        ->and($organization->statusMode())->toBe(OrganizationStatusMode::TrackingPaused)
        ->and($organization->canTrackProcesses())->toBeFalse()
        ->and($this->organization->notificationChannels()->where('is_active', true)->count())->toBe(0);
});

it('reactivates an organization with tracking and channels', function (): void {
    $this->organization->update([
        'is_active' => false,
        'is_process_tracking_enabled' => false,
    ]);
    $this->organization->notificationChannels()->update(['is_active' => false]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/admin/organizations/{$this->organization->id}/active-status", [
            'mode' => 'active',
        ]);

    $response->assertStatus(204);

    $organization = $this->organization->fresh();

    expect($organization->is_active)->toBeTrue()
        ->and($organization->is_process_tracking_enabled)->toBeTrue()
        ->and($organization->statusMode())->toBe(OrganizationStatusMode::Active)
        ->and($this->organization->notificationChannels()->where('is_active', true)->count())->toBe(3);
});

it('notifies organization users when notify_users is true on inactive mode', function (): void {
    $this->actingAs($this->user)
        ->postJson("/api/admin/organizations/{$this->organization->id}/active-status", [
            'mode' => 'inactive',
            'notify_users' => true,
        ])
        ->assertStatus(204);

    Notification::assertSentTo($this->owner, OrganizationDeactivatedNotification::class);
    Notification::assertSentTo($this->member, OrganizationDeactivatedNotification::class);
});

it('notifies organization users when notify_users is true on tracking_paused mode', function (): void {
    $this->actingAs($this->user)
        ->postJson("/api/admin/organizations/{$this->organization->id}/active-status", [
            'mode' => 'tracking_paused',
            'notify_users' => true,
        ])
        ->assertStatus(204);

    Notification::assertSentTo($this->owner, OrganizationDeactivatedNotification::class);
    Notification::assertSentTo($this->member, OrganizationDeactivatedNotification::class);
});

it('does not notify users when activating even if notify_users is true', function (): void {
    $this->organization->update([
        'is_active' => false,
        'is_process_tracking_enabled' => false,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/admin/organizations/{$this->organization->id}/active-status", [
            'mode' => 'active',
            'notify_users' => true,
        ])
        ->assertStatus(204);

    Notification::assertNothingSent();
});

it('does not change organization_processes pivots when changing status mode', function (): void {
    $process = Process::factory()->create();

    OrganizationProcess::query()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $process->id,
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => OrganizationProcessStatus::ACTIVE,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/admin/organizations/{$this->organization->id}/active-status", [
            'mode' => 'inactive',
        ])
        ->assertStatus(204);

    $pivot = OrganizationProcess::query()
        ->where('organization_id', $this->organization->id)
        ->where('process_id', $process->id)
        ->first();

    expect($pivot)->not->toBeNull()
        ->and($pivot->is_active)->toBeTrue()
        ->and($pivot->status)->toBe(OrganizationProcessStatus::ACTIVE);
});

it('requires admin authentication', function (): void {
    $this->postJson("/api/admin/organizations/{$this->organization->id}/active-status", [
        'mode' => 'inactive',
    ])->assertStatus(401);
});

it('returns 404 if organization does not exist', function (): void {
    $this->actingAs($this->user)
        ->postJson('/api/admin/organizations/non-existent-id/active-status', [
            'mode' => 'inactive',
        ])
        ->assertStatus(404);
});

it('validates mode is required and valid', function (): void {
    $this->actingAs($this->user)
        ->postJson("/api/admin/organizations/{$this->organization->id}/active-status", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['mode']);

    $this->actingAs($this->user)
        ->postJson("/api/admin/organizations/{$this->organization->id}/active-status", [
            'mode' => 'invalid',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['mode']);
});

it('builds a queued mail notification for status changes', function (): void {
    $notification = new OrganizationDeactivatedNotification(
        $this->organization,
        OrganizationStatusMode::TrackingPaused,
    );

    expect($notification->via($this->owner))->toBe(['mail'])
        ->and($notification->viaQueues())->toBe(['mail' => 'notifications-email']);

    $mailable = $notification->toMail($this->owner);

    expect($mailable->envelope()->subject)->toContain('Bufete Demo');
});

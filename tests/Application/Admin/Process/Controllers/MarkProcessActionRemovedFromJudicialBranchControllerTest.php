<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Src\Application\Admin\Process\Services\MarkProcessActionRemovedFromJudicialBranchService;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin-mark-removed@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);
});

it('marks an actuación as removed from judicial branch and exposes flags in admin APIs', function (): void {
    Mail::fake();

    $organization = Organization::factory()->create([
        'is_process_tracking_enabled' => true,
    ]);

    $process = Process::factory()->create();
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Auto de prueba retirado',
    ]);

    $digest = NotificationDigest::factory()->create([
        'organization_id' => $organization->id,
        'email_sent_at' => now(),
    ]);

    OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion',
        'severity_color' => null,
        'is_viewed' => true,
        'is_notified' => true,
        'is_email_notified' => true,
        'notified_at' => now(),
        'email_notified_at' => now(),
        'notification_digest_id' => $digest->id,
    ]);

    $pending = OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion_alerta',
        'severity_color' => 'red',
        'is_viewed' => false,
        'is_notified' => false,
        'is_email_notified' => false,
    ]);

    $response = $this->actingAs($this->user)->postJson(
        "/api/admin/processes/{$process->id}/actions/{$action->id}/mark-removed-from-judicial-branch",
        [
            'notify_organizations' => true,
            'reason' => 'Error del juzgado',
        ],
    );

    $response->assertOk();
    $response->assertJsonPath('action.removed_from_judicial_branch', true);
    $response->assertJsonPath('pending_notifications_silenced', 1);
    expect($response->json('notified_consolidados'))->toHaveCount(1);

    $action->refresh();
    expect($action->removed_from_judicial_branch_at)->not->toBeNull()
        ->and($action->removed_from_judicial_branch_reason)->toBe('Error del juzgado');

    $pending->refresh();
    expect($pending->is_email_notified)->toBeTrue()
        ->and($pending->notification_digest_id)->toBeNull();

    expect(
        OrganizationNotification::query()
            ->where('notification_type', MarkProcessActionRemovedFromJudicialBranchService::NOTIFICATION_TYPE)
            ->where('notifiable_id', $action->id)
            ->exists(),
    )->toBeTrue();

    $detail = $this->actingAs($this->user)->getJson("/api/admin/processes/{$process->id}");
    $detail->assertOk();
    $detail->assertJsonPath('process.removed_from_judicial_branch_count', 1);

    $actionsList = $this->actingAs($this->user)->getJson("/api/admin/processes/{$process->id}/actions");
    $actionsList->assertOk();
    $actionsList->assertJsonPath('data.0.removed_from_judicial_branch', true);
});

it('rejects marking the same actuación twice', function (): void {
    $process = Process::factory()->create();
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'removed_from_judicial_branch_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->postJson(
        "/api/admin/processes/{$process->id}/actions/{$action->id}/mark-removed-from-judicial-branch",
        ['notify_organizations' => false],
    );

    $response->assertStatus(422);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Src\Application\Shared\Notifications\ManualRegistrationCompletedNotification;
use Src\Application\Shared\Services\Notification\NotifyAppUserManualRegistrationCompletedService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Enums\ManualRegistrationRequestReason;
use Src\Domain\Process\Enums\ManualRegistrationRequestStatus;
use Src\Domain\Process\Models\ManualRegistrationRequest;
use Src\Domain\Process\Models\Process;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    Notification::fake();

    $this->organization = Organization::factory()->create(['name' => 'Org Notify']);
    $this->appUser = AppUser::factory()->create([
        'email' => 'lawyer@example.com',
        'name' => 'Laura',
        'last_name' => 'Gomez',
    ]);
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);

    $this->peer = AppUser::factory()->create(['email' => 'peer@example.com']);
    $this->peer->organizations()->attach($this->organization->id, ['is_owner' => false]);

    $this->admin = User::factory()->create([
        'email' => 'admin-complete@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);
    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->admin->roles()->attach($adminRole->id);

    $this->processNumber = '76892400300120260066300';
});

it('notifies requesting lawyer and org peers when a request is marked registered', function (): void {
    $process = Process::factory()->create([
        'process_number' => $this->processNumber,
        'is_manual_sync' => true,
        'is_private' => true,
    ]);
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => OrganizationProcessStatus::ACTIVE->value,
    ]);

    $request = ManualRegistrationRequest::query()->create([
        'organization_id' => $this->organization->id,
        'app_user_id' => $this->appUser->id,
        'process_number' => $this->processNumber,
        'reason' => ManualRegistrationRequestReason::Private,
        'status' => ManualRegistrationRequestStatus::Pending,
        'lawyer_role' => \Src\Domain\Process\Enums\ProcessLawyerRole::DEFENDANT,
        'process_class' => 'Verbal',
        'plaintiffs' => [['name' => 'Ana', 'identification' => null]],
        'defendants' => [['name' => 'Empresa', 'identification' => null]],
        'unassigned_actions_count' => 0,
        'discord_notified' => true,
    ]);

    $this->actingAs($this->admin)
        ->patchJson("/api/admin/processes/manual-registration-requests/{$request->id}", [
            'status' => 'registered',
        ])
        ->assertOk();

    Notification::assertSentTo($this->appUser, ManualRegistrationCompletedNotification::class);
    Notification::assertSentTo($this->peer, ManualRegistrationCompletedNotification::class);
});

it('does not notify the lawyer when the request is rejected', function (): void {
    $request = ManualRegistrationRequest::query()->create([
        'organization_id' => $this->organization->id,
        'app_user_id' => $this->appUser->id,
        'process_number' => $this->processNumber,
        'reason' => ManualRegistrationRequestReason::NotFound,
        'status' => ManualRegistrationRequestStatus::Pending,
        'unassigned_actions_count' => 0,
        'discord_notified' => true,
    ]);

    $this->actingAs($this->admin)
        ->patchJson("/api/admin/processes/manual-registration-requests/{$request->id}", [
            'status' => 'rejected',
        ])
        ->assertOk();

    Notification::assertNotSentTo($this->appUser, ManualRegistrationCompletedNotification::class);
});

it('builds completed notification payload for mail database and broadcast', function (): void {
    $request = ManualRegistrationRequest::query()->create([
        'organization_id' => $this->organization->id,
        'app_user_id' => $this->appUser->id,
        'process_number' => $this->processNumber,
        'reason' => ManualRegistrationRequestReason::Private,
        'status' => ManualRegistrationRequestStatus::Registered,
        'unassigned_actions_count' => 0,
        'discord_notified' => true,
        'resolved_at' => now(),
    ]);

    app(NotifyAppUserManualRegistrationCompletedService::class)->handle($request);

    Notification::assertSentTo($this->appUser, ManualRegistrationCompletedNotification::class, function (ManualRegistrationCompletedNotification $notification): bool {
        $data = $notification->toDatabase($this->appUser);

        return ($data['type'] ?? null) === 'manual-registration-completed'
            && $notification->broadcastType() === 'ManualRegistrationCompleted'
            && in_array('mail', $notification->via($this->appUser), true);
    });
});

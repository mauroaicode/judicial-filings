<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Src\Application\Shared\Notifications\ManualRegistrationRequestedNotification;
use Src\Application\Shared\Services\Notification\NotifyAdminsManualRegistrationRequestService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ManualRegistrationRequestReason;
use Src\Domain\Process\Enums\ManualRegistrationRequestStatus;
use Src\Domain\Process\Models\ManualRegistrationRequest;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

it('notifies only active admin users via database and broadcast notification', function (): void {
    Notification::fake();

    $org = Organization::factory()->create(['name' => 'Org WS']);
    $appUser = AppUser::factory()->create();
    $request = ManualRegistrationRequest::query()->create([
        'organization_id' => $org->id,
        'app_user_id' => $appUser->id,
        'process_number' => '76892400300120260066300',
        'reason' => ManualRegistrationRequestReason::Private,
        'status' => ManualRegistrationRequestStatus::Pending,
        'unassigned_actions_count' => 0,
        'discord_notified' => true,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);

    $activeAdmin = User::factory()->create([
        'email' => 'active-admin@example.com',
        'password' => Hash::make('password1234'),
        'state' => UserStatus::ACTIVE,
        'email_verified_at' => now(),
    ]);
    $activeAdmin->roles()->attach($adminRole->id);

    $inactiveAdmin = User::factory()->create([
        'email' => 'inactive-admin@example.com',
        'password' => Hash::make('password1234'),
        'state' => UserStatus::INACTIVE,
        'email_verified_at' => now(),
    ]);
    $inactiveAdmin->roles()->attach($adminRole->id);

    $nonAdmin = User::factory()->create([
        'email' => 'plain@example.com',
        'password' => Hash::make('password1234'),
        'state' => UserStatus::ACTIVE,
        'email_verified_at' => now(),
    ]);

    app(NotifyAdminsManualRegistrationRequestService::class)->handle($request);

    Notification::assertSentTo($activeAdmin, ManualRegistrationRequestedNotification::class, function (ManualRegistrationRequestedNotification $notification) use ($request): bool {
        $data = $notification->toDatabase($request);

        return ($data['type'] ?? null) === 'manual-registration-requested'
            && ($data['request_id'] ?? null) === $request->id
            && $notification->broadcastType() === 'ManualRegistrationRequested';
    });

    Notification::assertNotSentTo($inactiveAdmin, ManualRegistrationRequestedNotification::class);
    Notification::assertNotSentTo($nonAdmin, ManualRegistrationRequestedNotification::class);
});

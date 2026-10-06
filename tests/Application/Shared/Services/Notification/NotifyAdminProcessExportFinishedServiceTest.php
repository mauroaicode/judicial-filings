<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Src\Application\Shared\Notifications\ProcessExportFinishedNotification;
use Src\Application\Shared\Services\Notification\NotifyAdminProcessExportFinishedService;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessExportStatus;
use Src\Domain\Process\Models\ProcessExport;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

it('notifies the requesting admin via database and broadcast', function (): void {
    Notification::fake();

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);

    $requester = User::factory()->create([
        'email' => 'export-requester@example.com',
        'password' => Hash::make('password1234'),
        'state' => UserStatus::ACTIVE,
        'email_verified_at' => now(),
    ]);
    $requester->roles()->attach($adminRole->id);

    $otherAdmin = User::factory()->create([
        'email' => 'other-admin-export@example.com',
        'password' => Hash::make('password1234'),
        'state' => UserStatus::ACTIVE,
        'email_verified_at' => now(),
    ]);
    $otherAdmin->roles()->attach($adminRole->id);

    $organization = Organization::factory()->create(['name' => 'Org Export WS']);
    $export = ProcessExport::factory()->completed()->create([
        'organization_id' => $organization->id,
        'requested_by' => $requester->id,
        'row_count' => 12,
        'filters' => ['status' => 'active', 'include_plaintiffs' => true],
    ]);

    app(NotifyAdminProcessExportFinishedService::class)->handle($export);

    Notification::assertSentTo($requester, ProcessExportFinishedNotification::class, function (ProcessExportFinishedNotification $notification) use ($export, $requester): bool {
        $data = $notification->toDatabase($requester);

        return ($data['type'] ?? null) === 'process-export-finished'
            && ($data['export_id'] ?? null) === $export->id
            && ($data['status'] ?? null) === ProcessExportStatus::Completed->value
            && $notification->broadcastType() === 'ProcessExportFinished';
    });

    Notification::assertNotSentTo($otherAdmin, ProcessExportFinishedNotification::class);
});

it('falls back to all active admins when requester is missing', function (): void {
    Notification::fake();

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);

    $admin = User::factory()->create([
        'email' => 'fallback-admin-export@example.com',
        'password' => Hash::make('password1234'),
        'state' => UserStatus::ACTIVE,
        'email_verified_at' => now(),
    ]);
    $admin->roles()->attach($adminRole->id);

    $export = ProcessExport::factory()->completed()->create([
        'organization_id' => Organization::factory()->create()->id,
        'requested_by' => null,
    ]);

    app(NotifyAdminProcessExportFinishedService::class)->handle($export);

    Notification::assertSentTo($admin, ProcessExportFinishedNotification::class);
});

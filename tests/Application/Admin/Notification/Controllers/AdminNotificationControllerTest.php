<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Src\Application\Admin\Notification\Controllers\AdminNotificationController;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

function insertAdminNotification(User $user): string
{
    $id = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'TestNotification',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'data' => json_encode(['message' => 'test']),
        'read_at' => null,
        'opened_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin-notifications@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);
});

it('ensures admin notification controller exists', function (): void {
    expect(class_exists(AdminNotificationController::class))->toBeTrue();
});

it('returns unread_count and new_count for admin', function (): void {
    insertAdminNotification($this->user);
    insertAdminNotification($this->user);
    insertAdminNotification($this->user);

    $this->actingAs($this->user)
        ->getJson('/api/admin/notifications/unread-count')
        ->assertOk()
        ->assertJson([
            'unread_count' => 3,
            'new_count' => 3,
        ]);
});

it('marks all notifications as opened without marking as read', function (): void {
    insertAdminNotification($this->user);
    insertAdminNotification($this->user);

    $this->actingAs($this->user)
        ->postJson('/api/admin/notifications/mark-all-opened')
        ->assertOk();

    $this->actingAs($this->user)
        ->getJson('/api/admin/notifications/unread-count')
        ->assertOk()
        ->assertJson([
            'unread_count' => 2,
            'new_count' => 0,
        ]);
});

it('sets opened_at when marking a notification as read', function (): void {
    $notificationId = insertAdminNotification($this->user);

    $this->actingAs($this->user)
        ->postJson("/api/admin/notifications/{$notificationId}/read")
        ->assertOk();

    $this->actingAs($this->user)
        ->getJson('/api/admin/notifications/unread-count')
        ->assertOk()
        ->assertJson([
            'unread_count' => 0,
            'new_count' => 0,
        ]);
});

it('marks all as read and opened', function (): void {
    insertAdminNotification($this->user);
    insertAdminNotification($this->user);

    $this->actingAs($this->user)
        ->postJson('/api/admin/notifications/mark-all-read')
        ->assertOk();

    $this->actingAs($this->user)
        ->getJson('/api/admin/notifications/unread-count')
        ->assertOk()
        ->assertJson([
            'unread_count' => 0,
            'new_count' => 0,
        ]);
});

it('includes opened_at in notification resource', function (): void {
    $notificationId = insertAdminNotification($this->user);

    $this->actingAs($this->user)
        ->getJson('/api/admin/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.id', $notificationId)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'opened_at',
                ],
            ],
        ]);
});

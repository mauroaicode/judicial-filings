<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Src\Domain\AppUser\Models\AppUser;

/**
 * Helper to insert a raw database notification for a user.
 */
function insertNotification(AppUser $user): string
{
    $id = (string) Str::uuid();

    \Illuminate\Support\Facades\DB::table('notifications')->insert([
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

it('can get unread and new notification count', function (): void {
    /** @var AppUser $user */
    $user = AppUser::factory()->create();

    insertNotification($user);
    insertNotification($user);
    insertNotification($user);

    $this->actingAs($user)
        ->getJson('/api/app-user/notifications/unread-count')
        ->assertOk()
        ->assertJson([
            'unread_count' => 3,
            'new_count' => 3,
        ]);
});

it('can mark all notifications as opened', function (): void {
    /** @var AppUser $user */
    $user = AppUser::factory()->create();

    insertNotification($user);
    insertNotification($user);

    $this->actingAs($user)
        ->postJson('/api/app-user/notifications/mark-all-opened')
        ->assertOk();

    $this->actingAs($user)
        ->getJson('/api/app-user/notifications/unread-count')
        ->assertOk()
        ->assertJson([
            'unread_count' => 2,
            'new_count' => 0,
        ]);
});

it('marks as opened when marking as read', function (): void {
    /** @var AppUser $user */
    $user = AppUser::factory()->create();

    $notificationId = insertNotification($user);

    $this->actingAs($user)
        ->postJson("/api/app-user/notifications/{$notificationId}/read")
        ->assertOk();

    $this->actingAs($user)
        ->getJson('/api/app-user/notifications/unread-count')
        ->assertOk()
        ->assertJson([
            'unread_count' => 0,
            'new_count' => 0,
        ]);
});

it('can mark all as read and opened', function (): void {
    /** @var AppUser $user */
    $user = AppUser::factory()->create();

    insertNotification($user);
    insertNotification($user);

    $this->actingAs($user)
        ->postJson('/api/app-user/notifications/mark-all-read')
        ->assertOk();

    $this->actingAs($user)
        ->getJson('/api/app-user/notifications/unread-count')
        ->assertOk()
        ->assertJson([
            'unread_count' => 0,
            'new_count' => 0,
        ]);
});

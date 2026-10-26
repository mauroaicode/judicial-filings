<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Enums\NotificationDigestOpenedVia;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Notification\Models\NotificationDigestView;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin-digest-views@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);
    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create(['name' => 'Bufete Alfa']);

    $this->digest = NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'viewed_at' => '2030-01-16 09:30:00',
    ]);
});

it('requires authentication to list who opened a digest', function (): void {
    $this->getJson('/api/admin/notification-digests/'.$this->digest->id.'/views')
        ->assertUnauthorized();
});

it('forbids non-admin users from listing who opened a digest', function (): void {
    $plain = User::factory()->create([
        'email' => 'plain-digest-views@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $this->actingAs($plain)
        ->getJson('/api/admin/notification-digests/'.$this->digest->id.'/views')
        ->assertForbidden();
});

it('returns not found when the digest does not exist', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/admin/notification-digests/00000000-0000-4000-8000-000000000000/views')
        ->assertNotFound();
});

it('lists each person who opened the digest, oldest first, without recording a view', function (): void {
    app()->setLocale('es');

    $ana = AppUser::factory()->create([
        'name' => 'Ana',
        'last_name' => 'Pérez',
        'email' => 'ana-digest-view@example.com',
    ]);
    $luis = AppUser::factory()->create([
        'name' => 'Luis',
        'last_name' => 'Gómez',
        'email' => 'luis-digest-view@example.com',
    ]);

    NotificationDigestView::query()->create([
        'notification_digest_id' => $this->digest->id,
        'organization_id' => $this->organization->id,
        'app_user_id' => $luis->id,
        'opened_via' => NotificationDigestOpenedVia::App,
        'viewed_at' => '2030-01-16 10:15:00',
    ]);
    $anaView = NotificationDigestView::query()->create([
        'notification_digest_id' => $this->digest->id,
        'organization_id' => $this->organization->id,
        'app_user_id' => $ana->id,
        'opened_via' => NotificationDigestOpenedVia::Email,
        'viewed_at' => '2030-01-16 09:30:00',
    ]);

    $otherDigest = NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    NotificationDigestView::query()->create([
        'notification_digest_id' => $otherDigest->id,
        'organization_id' => $this->organization->id,
        'app_user_id' => $ana->id,
        'opened_via' => NotificationDigestOpenedVia::App,
        'viewed_at' => '2030-01-16 11:00:00',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/notification-digests/'.$this->digest->id.'/views');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $anaView->id)
        ->assertJsonPath('data.0.app_user_id', $ana->id)
        ->assertJsonPath('data.0.app_user_name', 'Ana')
        ->assertJsonPath('data.0.app_user_last_name', 'Pérez')
        ->assertJsonPath('data.0.email', 'ana-digest-view@example.com')
        ->assertJsonPath('data.0.viewed_at', DateFormatHelper::formatDateWithTime('2030-01-16 09:30:00'))
        ->assertJsonPath('data.0.opened_via', 'email')
        ->assertJsonPath('data.1.app_user_name', 'Luis')
        ->assertJsonPath('data.1.opened_via', 'app');

    expect(NotificationDigestView::query()->where('notification_digest_id', $this->digest->id)->count())->toBe(2)
        ->and($this->digest->fresh()->viewed_at?->toDateTimeString())->toBe('2030-01-16 09:30:00');
});

it('returns an empty list when nobody has opened the digest', function (): void {
    $unviewed = NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'viewed_at' => null,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/admin/notification-digests/'.$unviewed->id.'/views')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

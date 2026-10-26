<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Enums\NotificationDigestOpenedVia;
use Src\Domain\Notification\Events\NotificationDigestViewed;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Notification\Models\NotificationDigestView;
use Src\Domain\Organization\Models\Organization;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->organization = Organization::factory()->create(['name' => 'Organización Vista']);
    $this->appUser = AppUser::factory()->create([
        'name' => 'Ana',
        'last_name' => 'Pérez',
    ]);
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);

    $this->digest = NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'viewed_at' => null,
    ]);

    actingAs($this->appUser, 'sanctum');
    Event::fake([NotificationDigestViewed::class]);
});

it('marks the digest as viewed and stores the app user when opened from the app', function () {
    $this->freezeTime();

    $response = getJson('/api/app-user/notification-digests/'.$this->digest->id.'?opened_via=app');

    $response->assertOk();

    $this->digest->refresh();
    expect($this->digest->viewed_at?->toDateTimeString())->toBe(now()->toDateTimeString());

    $view = NotificationDigestView::query()
        ->where('notification_digest_id', $this->digest->id)
        ->where('app_user_id', $this->appUser->id)
        ->first();
    expect($view)->not->toBeNull()
        ->and($view->notification_digest_id)->toBe($this->digest->id)
        ->and($view->organization_id)->toBe($this->organization->id)
        ->and($view->app_user_id)->toBe($this->appUser->id)
        ->and($view->opened_via)->toBe(NotificationDigestOpenedVia::App);

    Event::assertDispatched(NotificationDigestViewed::class, function (NotificationDigestViewed $event): bool {
        return $event->notificationDigestId === $this->digest->id
            && $event->organizationId === $this->organization->id
            && $event->organizationName === 'Organización Vista'
            && $event->appUserId === $this->appUser->id
            && $event->appUserName === 'Ana'
            && $event->appUserLastName === 'Pérez'
            && $event->openedVia === 'app'
            && $event->broadcastQueue === 'notifications';
    });
});

it('marks a view opened from the email link', function () {
    getJson('/api/app-user/notification-digests/'.$this->digest->id.'?opened_via=email')->assertOk();

    expect(NotificationDigestView::query()->where('notification_digest_id', $this->digest->id)->sole()->opened_via)
        ->toBe(NotificationDigestOpenedVia::Email);
});

it('does not duplicate the view when the same app user opens the digest again', function () {
    getJson('/api/app-user/notification-digests/'.$this->digest->id)->assertOk();
    $firstViewedAt = $this->digest->fresh()->viewed_at;

    $this->travel(5)->minutes();

    getJson('/api/app-user/notification-digests/'.$this->digest->id.'?page=2&opened_via=email')->assertOk();

    $views = NotificationDigestView::query()->where('notification_digest_id', $this->digest->id);

    expect($views->count())->toBe(1)
        ->and($this->digest->fresh()->viewed_at?->toDateTimeString())->toBe($firstViewedAt?->toDateTimeString())
        ->and($views->sole()->opened_via)->toBe(NotificationDigestOpenedVia::App);

    Event::assertDispatchedTimes(NotificationDigestViewed::class, 1);
});

it('stores every app user of the organization that opens the digest without repeating them', function () {
    $coworker = AppUser::factory()->create();
    $coworker->organizations()->attach($this->organization->id);

    getJson('/api/app-user/notification-digests/'.$this->digest->id)->assertOk();

    actingAs($coworker, 'sanctum');
    getJson('/api/app-user/notification-digests/'.$this->digest->id.'?opened_via=email')->assertOk();
    getJson('/api/app-user/notification-digests/'.$this->digest->id.'?opened_via=email')->assertOk();

    expect(NotificationDigestView::query()->where('notification_digest_id', $this->digest->id)->count())->toBe(2)
        ->and(NotificationDigestView::query()->where('notification_digest_id', $this->digest->id)->where('app_user_id', $coworker->id)->count())->toBe(1)
        ->and($this->digest->fresh()->viewed_at)->not->toBeNull();

    Event::assertDispatchedTimes(NotificationDigestViewed::class, 2);
});

it('does not record a view when listing digest history', function () {
    getJson('/api/app-user/notification-digests/history')->assertOk();

    expect(NotificationDigestView::query()->where('notification_digest_id', $this->digest->id)->count())->toBe(0)
        ->and($this->digest->fresh()->viewed_at)->toBeNull();

    Event::assertNotDispatched(NotificationDigestViewed::class);
});

it('does not record a view for a digest of another organization', function () {
    $otherDigest = NotificationDigest::factory()->create();

    getJson('/api/app-user/notification-digests/'.$otherDigest->id)->assertNotFound();

    expect(NotificationDigestView::query()->where('notification_digest_id', $otherDigest->id)->count())->toBe(0)
        ->and($otherDigest->fresh()->viewed_at)->toBeNull();

    Event::assertNotDispatched(NotificationDigestViewed::class);
});

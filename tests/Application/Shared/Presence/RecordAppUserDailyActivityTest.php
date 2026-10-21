<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Src\Application\Shared\Presence\HttpIpLocationLookup;
use Src\Application\Shared\Presence\IpLocation;
use Src\Application\Shared\Presence\IpLocationLookup;
use Src\Application\Shared\Presence\Services\RecordAppUserDailyActivityService;
use Src\Domain\AppUser\Events\AppUserActivityRecorded;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\AppUser\Models\AppUserDailyActivity;
use Src\Domain\Organization\Models\Organization;

beforeEach(function (): void {
    Cache::store((string) config('presence.cache_store'))->flush();
    $this->appUser = AppUser::factory()->create();
});

it('stores one daily row with ip and place and increments login only on login', function (): void {
    $this->app->instance(IpLocationLookup::class, new class implements IpLocationLookup
    {
        public function locate(string $ip): ?IpLocation
        {
            return new IpLocation('Colombia', 'Antioquia', 'Medellín');
        }
    });

    $seenAt = now()->setTime(8, 0);
    $this->travelTo($seenAt);

    app(RecordAppUserDailyActivityService::class)->handle($this->appUser->id, '8.8.8.8', true);
    app(RecordAppUserDailyActivityService::class)->handle($this->appUser->id, '8.8.8.8', false);

    $this->travelTo($seenAt->copy()->addHour());
    app(RecordAppUserDailyActivityService::class)->handle($this->appUser->id, '8.8.8.8', true);

    $activity = AppUserDailyActivity::query()->where('app_user_id', $this->appUser->id)->first();

    expect(AppUserDailyActivity::query()->where('app_user_id', $this->appUser->id)->count())->toBe(1)
        ->and($activity)->not->toBeNull()
        ->and($activity->login_count)->toBe(2)
        ->and($activity->ip_address)->toBe('8.8.8.8')
        ->and($activity->city)->toBe('Medellín')
        ->and($activity->region)->toBe('Antioquia')
        ->and($activity->country)->toBe('Colombia')
        ->and($activity->first_seen_at->format('H:i'))->toBe('08:00')
        ->and($activity->last_seen_at->format('H:i'))->toBe('09:00')
        ->and($activity->organization_id)->toBeNull();
});

it('writes one row per organization with the same login count', function (): void {
    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();
    $this->appUser->organizations()->attach($firstOrganization->id, ['is_owner' => true]);
    $this->appUser->organizations()->attach($secondOrganization->id, ['is_owner' => false]);

    app(RecordAppUserDailyActivityService::class)->handle($this->appUser->id, '127.0.0.1', true);

    $activities = AppUserDailyActivity::query()->where('app_user_id', $this->appUser->id)->get();

    expect($activities)->toHaveCount(2)
        ->and($activities->pluck('organization_id')->sort()->values()->all())
        ->toEqual(collect([$firstOrganization->id, $secondOrganization->id])->sort()->values()->all())
        ->and($activities->every(fn (AppUserDailyActivity $activity): bool => $activity->login_count === 1))->toBeTrue();
});

it('notifies admins once after the daily activity is stored', function (): void {
    Event::fake([AppUserActivityRecorded::class]);

    app(RecordAppUserDailyActivityService::class)->handle($this->appUser->id, '127.0.0.1', true);

    Event::assertDispatchedTimes(AppUserActivityRecorded::class, 1);
    Event::assertDispatched(
        AppUserActivityRecorded::class,
        fn (AppUserActivityRecorded $event): bool => $event->appUserId === $this->appUser->id
            && $event->incrementLogin === true
            && $event->activityDate === now()->toDateString()
            && $event->broadcastAs() === 'AppUserActivityRecorded',
    );
});

it('keeps the ip and leaves the place empty when the address is private', function (): void {
    app(RecordAppUserDailyActivityService::class)->handle($this->appUser->id, '127.0.0.1', false);

    $activity = AppUserDailyActivity::query()->where('app_user_id', $this->appUser->id)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->ip_address)->toBe('127.0.0.1')
        ->and($activity->city)->toBeNull()
        ->and($activity->country)->toBeNull()
        ->and($activity->login_count)->toBe(0);
});

it('resolves a public ip once and skips private addresses', function (): void {
    Http::fake([
        'https://ipwho.is/*' => Http::response([
            'success' => true,
            'country' => 'Colombia',
            'region' => 'Cundinamarca',
            'city' => 'Bogotá',
        ]),
    ]);

    $lookup = app(HttpIpLocationLookup::class);

    $location = $lookup->locate('8.8.8.8');
    $cached = $lookup->locate('8.8.8.8');

    expect($location)->not->toBeNull()
        ->and($location->city)->toBe('Bogotá')
        ->and($cached?->region)->toBe('Cundinamarca')
        ->and($lookup->locate('10.0.0.8'))->toBeNull();

    Http::assertSentCount(1);
});

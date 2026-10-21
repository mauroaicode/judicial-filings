<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Src\Application\Shared\Jobs\RecordAppUserDailyActivityJob;
use Src\Application\Shared\Presence\AuthorizeAppUserPresenceChannel;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\AppUser\Models\AppUserDailyActivity;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create(['is_active' => true]);
    $this->appUser = AppUser::factory()->create([
        'identification' => '1099000111',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);
});

it('records the ip when the app user logs in', function (): void {
    Http::fake([
        'https://ipwho.is/*' => Http::response([
            'success' => true,
            'country' => 'Colombia',
            'region' => 'Antioquia',
            'city' => 'Medellín',
        ]),
    ]);

    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->postJson('/api/app-user/login', [
            'identification' => '1099000111',
            'password' => 'password1234',
        ]);

    $response->assertStatus(200);

    $activity = AppUserDailyActivity::query()->where('app_user_id', $this->appUser->id)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->login_count)->toBe(1)
        ->and($activity->ip_address)->toBe('203.0.113.10')
        ->and($activity->city)->toBe('Medellín')
        ->and($activity->organization_id)->toBe($this->organization->id);
});

it('counts an already authenticated user once inside the debounce window', function (): void {
    $token = $this->appUser->createToken('test')->plainTextToken;
    $this->travelTo(now()->setTime(10, 0));

    $this->withToken($token)->getJson('/api/app-user/dashboard/stats')->assertStatus(200);
    $firstSeen = AppUserDailyActivity::query()->where('app_user_id', $this->appUser->id)->first();

    $this->travel(10)->minutes();
    $this->withToken($token)->getJson('/api/app-user/dashboard/stats')->assertStatus(200);

    $activity = AppUserDailyActivity::query()->where('app_user_id', $this->appUser->id)->first();

    expect(AppUserDailyActivity::query()->where('app_user_id', $this->appUser->id)->count())->toBe(1)
        ->and($activity->login_count)->toBe(0)
        ->and($activity->last_seen_at->format('Y-m-d H:i:s'))->toBe($firstSeen->last_seen_at->format('Y-m-d H:i:s'));

    $this->travel(16)->minutes();
    $this->withToken($token)->getJson('/api/app-user/dashboard/stats')->assertStatus(200);

    $activity->refresh();

    expect($activity->last_seen_at->greaterThan($firstSeen->last_seen_at))->toBeTrue()
        ->and($activity->login_count)->toBe(0);
});

it('lets an app user join the presence channel and refuses a user without the admin role', function (): void {
    Bus::fake();

    $payload = app(AuthorizeAppUserPresenceChannel::class)($this->appUser);

    expect($payload)->toBeArray()
        ->and($payload['id'])->toBe($this->appUser->id);

    Bus::assertDispatched(RecordAppUserDailyActivityJob::class);

    $user = User::factory()->create([
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    expect(app(AuthorizeAppUserPresenceChannel::class)($user))->toBeFalse();

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $user->roles()->attach($adminRole->id);

    $adminPayload = app(AuthorizeAppUserPresenceChannel::class)($user->fresh());

    expect($adminPayload)->toBeArray()
        ->and($adminPayload['guard'])->toBe('admin');

    Bus::assertDispatchedTimes(RecordAppUserDailyActivityJob::class, 1);
});

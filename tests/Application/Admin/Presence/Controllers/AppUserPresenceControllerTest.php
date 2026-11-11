<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Application\Admin\Presence\PresenceChannelRoster;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\AppUser\Models\AppUserDailyActivity;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin@presence.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create(['name' => 'Bufete Central']);
    $this->appUser = AppUser::factory()->create([
        'name' => 'Ana',
        'last_name' => 'Pérez',
        'identification' => '1099888777',
    ]);
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);

    $this->roster = new class implements PresenceChannelRoster
    {
        /** @var list<string>|null */
        public ?array $ids = [];

        public function memberIds(): ?array
        {
            return $this->ids;
        }
    };

    $this->app->instance(PresenceChannelRoster::class, $this->roster);
});

it('requires authentication to list presence', function (): void {
    $this->getJson('/api/admin/presence/today')->assertStatus(401);
    $this->getJson('/api/admin/presence/online')->assertStatus(401);
    $this->getJson('/api/admin/presence/organizations')->assertStatus(401);
});

it('rejects an app user', function (): void {
    $token = $this->appUser->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/admin/presence/today')->assertStatus(403);
    $this->withToken($token)->getJson('/api/admin/presence/online')->assertStatus(403);
    $this->withToken($token)->getJson('/api/admin/presence/organizations')->assertStatus(403);
});

it('lists who entered today with ip and place', function (): void {
    AppUserDailyActivity::query()->create([
        'app_user_id' => $this->appUser->id,
        'organization_id' => $this->organization->id,
        'activity_date' => now()->toDateString(),
        'first_seen_at' => now()->subHour(),
        'last_seen_at' => now(),
        'login_count' => 1,
        'ip_address' => '190.1.2.3',
        'country' => 'Colombia',
        'region' => 'Antioquia',
        'city' => 'Medellín',
    ]);

    AppUserDailyActivity::query()->create([
        'app_user_id' => $this->appUser->id,
        'organization_id' => $this->organization->id,
        'activity_date' => now()->subDay()->toDateString(),
        'first_seen_at' => now()->subDay(),
        'last_seen_at' => now()->subDay(),
        'login_count' => 1,
        'ip_address' => '190.9.9.9',
        'country' => 'Colombia',
        'region' => 'Valle del Cauca',
        'city' => 'Cali',
    ]);

    $this->roster->ids = [$this->appUser->id];

    $response = $this->actingAs($this->user)->getJson(
        '/api/admin/presence/today?organization_id='.$this->organization->id
    );

    $response->assertStatus(200);
    $response->assertJsonPath('total', 1);
    $response->assertJsonPath('data.0.name', 'Ana');
    $response->assertJsonPath('data.0.place', 'Medellín, Antioquia, Colombia');
    $response->assertJsonPath('data.0.ip_address', '190.1.2.3');
    $response->assertJsonPath('data.0.login_count', 1);
    $response->assertJsonPath('data.0.is_online', true);
    $response->assertJsonPath('data.0.organization_id', $this->organization->id);
    $response->assertJsonPath('data.0.organization_name', 'Bufete Central');
    $response->assertJsonPath('data.0.organizations.0.name', 'Bufete Central');

    $yesterday = $this->actingAs($this->user)->getJson(
        '/api/admin/presence/today?organization_id='.$this->organization->id.'&date='.now()->subDay()->toDateString()
    );

    $yesterday->assertStatus(200);
    $yesterday->assertJsonPath('data.0.city', 'Cali');
    $yesterday->assertJsonPath('total', 1);
});

it('rejects an invalid date', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/admin/presence/today?date=21-10-2026')
        ->assertStatus(422);
});

it('returns who is online and reports when presence is unavailable', function (): void {
    AppUserDailyActivity::query()->create([
        'app_user_id' => $this->appUser->id,
        'activity_date' => now()->toDateString(),
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'login_count' => 0,
        'ip_address' => '190.1.2.3',
        'country' => 'Colombia',
        'region' => 'Antioquia',
        'city' => 'Medellín',
    ]);

    $this->roster->ids = [$this->appUser->id, 'not-an-app-user'];

    $online = $this->actingAs($this->user)->getJson('/api/admin/presence/online');

    $online->assertStatus(200);
    $online->assertJsonPath('presence_available', true);
    $online->assertJsonPath('total', 1);
    $online->assertJsonPath('users.0.id', $this->appUser->id);
    $online->assertJsonPath('users.0.place', 'Medellín, Antioquia, Colombia');

    $this->roster->ids = null;

    $unavailable = $this->actingAs($this->user)->getJson('/api/admin/presence/online');

    $unavailable->assertStatus(200);
    $unavailable->assertJsonPath('presence_available', false);
    $unavailable->assertJsonPath('total', 0);
    $unavailable->assertJsonPath('users', []);
});

it('deletes activity older than the retention window', function (): void {
    AppUserDailyActivity::query()->create([
        'app_user_id' => $this->appUser->id,
        'activity_date' => now()->subMonths(13)->toDateString(),
        'first_seen_at' => now()->subMonths(13),
        'last_seen_at' => now()->subMonths(13),
        'login_count' => 1,
    ]);

    $this->artisan('presence:purge-daily-activity')->assertSuccessful();

    expect(AppUserDailyActivity::query()->where('app_user_id', $this->appUser->id)->exists())->toBeFalse();
});

it('filters activity by organization and summarizes logins and days online in a period', function (): void {
    $otherOrganization = Organization::factory()->create(['name' => 'Otro Bufete']);
    $otherUser = AppUser::factory()->create(['name' => 'Luis']);
    $otherUser->organizations()->attach($otherOrganization->id, ['is_owner' => true]);

    $start = now()->startOfMonth();
    $firstDay = $start->toDateString();
    $secondDay = $start->copy()->addDay()->toDateString();
    $periodEnd = $start->copy()->addDays(2)->toDateString();

    AppUserDailyActivity::query()->create([
        'app_user_id' => $this->appUser->id,
        'organization_id' => $this->organization->id,
        'activity_date' => $firstDay,
        'first_seen_at' => $start->copy()->setTime(8, 0),
        'last_seen_at' => $start->copy()->setTime(9, 0),
        'login_count' => 2,
    ]);
    AppUserDailyActivity::query()->create([
        'app_user_id' => $this->appUser->id,
        'organization_id' => $this->organization->id,
        'activity_date' => $secondDay,
        'first_seen_at' => $start->copy()->addDay()->setTime(8, 0),
        'last_seen_at' => $start->copy()->addDay()->setTime(10, 0),
        'login_count' => 1,
    ]);
    AppUserDailyActivity::query()->create([
        'app_user_id' => $otherUser->id,
        'organization_id' => $otherOrganization->id,
        'activity_date' => $firstDay,
        'first_seen_at' => $start->copy()->setTime(8, 0),
        'last_seen_at' => $start->copy()->setTime(8, 30),
        'login_count' => 4,
    ]);

    $filtered = $this->actingAs($this->user)->getJson(
        '/api/admin/presence/today?organization_id='.$this->organization->id.'&from='.$firstDay.'&to='.$periodEnd
    );

    $filtered->assertStatus(200);
    $filtered->assertJsonPath('total', 2);
    $filtered->assertJsonPath('data.0.organization_id', $this->organization->id);
    $filtered->assertJsonPath('data.1.organization_id', $this->organization->id);

    $summary = $this->actingAs($this->user)->getJson(
        '/api/admin/presence/summary?organization_id='.$this->organization->id.'&from='.$firstDay.'&to='.$periodEnd
    );

    $summary->assertStatus(200);
    $summary->assertJsonPath('organization_id', $this->organization->id);
    $summary->assertJsonPath('totals.users', 1);
    $summary->assertJsonPath('totals.login_count', 3);
    $summary->assertJsonPath('totals.days_online', 2);
    $summary->assertJsonPath('data.0.app_user_id', $this->appUser->id);
    $summary->assertJsonPath('data.0.organization_name', 'Bufete Central');
    $summary->assertJsonPath('data.0.login_count', 3);
    $summary->assertJsonPath('data.0.days_online', 2);

    $this->roster->ids = [$this->appUser->id, $otherUser->id];

    $online = $this->actingAs($this->user)->getJson(
        '/api/admin/presence/online?organization_id='.$this->organization->id
    );

    $online->assertStatus(200);
    $online->assertJsonPath('total', 1);
    $online->assertJsonPath('users.0.id', $this->appUser->id);
});

it('lists organizations that never connected and those inactive for too long', function (): void {
    $prefix = 'PresenceOrg '.uniqid('', true);
    $never = Organization::factory()->create(['name' => $prefix.' Never', 'is_active' => true]);
    $stale = Organization::factory()->create(['name' => $prefix.' Stale', 'is_active' => true]);
    $recent = Organization::factory()->create(['name' => $prefix.' Recent', 'is_active' => true]);
    $inactiveOrg = Organization::factory()->create(['name' => $prefix.' Off', 'is_active' => false]);

    $staleUser = AppUser::factory()->create();
    $staleUser->organizations()->attach($stale->id, ['is_owner' => true]);
    $recentUser = AppUser::factory()->create();
    $recentUser->organizations()->attach($recent->id, ['is_owner' => true]);

    AppUserDailyActivity::query()->create([
        'app_user_id' => $staleUser->id,
        'organization_id' => $stale->id,
        'activity_date' => now()->subDays(45)->toDateString(),
        'first_seen_at' => now()->subDays(45),
        'last_seen_at' => now()->subDays(45),
        'login_count' => 1,
    ]);
    AppUserDailyActivity::query()->create([
        'app_user_id' => $recentUser->id,
        'organization_id' => $recent->id,
        'activity_date' => now()->toDateString(),
        'first_seen_at' => now()->subHour(),
        'last_seen_at' => now(),
        'login_count' => 1,
    ]);

    $neverList = $this->actingAs($this->user)->getJson(
        '/api/admin/presence/organizations?status=never&search='.urlencode($prefix).'&inactive_days=30'
    );

    $neverList->assertStatus(200);
    $neverList->assertJsonPath('status', 'never');
    $neverList->assertJsonPath('inactive_days', 30);
    $neverList->assertJsonPath('totals.never', 1);
    $neverList->assertJsonPath('totals.stale', 1);
    $neverList->assertJsonPath('totals.recent', 1);
    $neverList->assertJsonPath('total', 1);
    $neverList->assertJsonPath('data.0.organization_id', $never->id);
    $neverList->assertJsonPath('data.0.status', 'never');
    $neverList->assertJsonPath('data.0.last_seen_at', null);
    $neverList->assertJsonPath('data.0.days_inactive', null);

    $staleList = $this->actingAs($this->user)->getJson(
        '/api/admin/presence/organizations?status=stale&search='.urlencode($prefix).'&inactive_days=30'
    );

    $staleList->assertStatus(200);
    $staleList->assertJsonPath('total', 1);
    $staleList->assertJsonPath('data.0.organization_id', $stale->id);
    $staleList->assertJsonPath('data.0.status', 'stale');
    $staleList->assertJsonPath('data.0.users_count', 1);
    $staleList->assertJsonPath('data.0.days_inactive', 45);

    $all = $this->actingAs($this->user)->getJson(
        '/api/admin/presence/organizations?status=all&search='.urlencode($prefix).'&inactive_days=30'
    );

    $all->assertStatus(200);
    $all->assertJsonPath('total', 3);
    $all->assertJsonPath('data.0.organization_id', $never->id);
    $all->assertJsonPath('data.1.organization_id', $stale->id);
    $all->assertJsonPath('data.2.organization_id', $recent->id);
    $all->assertJsonPath('data.2.status', 'recent');

    $withInactive = $this->actingAs($this->user)->getJson(
        '/api/admin/presence/organizations?status=never&search='.urlencode($prefix).'&is_active='
    );

    $withInactive->assertStatus(200);
    $withInactive->assertJsonPath('total', 2);
    expect(collect($withInactive->json('data'))->pluck('organization_id')->all())
        ->toContain($never->id, $inactiveOrg->id);
});

it('rejects an invalid organization presence status', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/admin/presence/organizations?status=missing')
        ->assertStatus(422);
});

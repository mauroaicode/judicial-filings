<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessActionAlertHighlight;

beforeEach(function (): void {
    if (! Schema::hasTable('alert_actions_keywords')) {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_02_05_000000_create_alert_actions_keywords_table.php',
        ]);
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_02_05_000001_create_process_action_alert_action_keyword_table.php',
        ]);
        (new \Database\Seeders\AlertKeywordSeeder)->run();
    }

    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create([
        'email' => 'notifications@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);
});

it('requires authentication for organization notifications index', function (): void {
    $response = $this->getJson('/api/app-user/organization-notifications?type=actuacion');

    $response->assertStatus(401);
});

it('returns 422 when type is missing', function (): void {
    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/organization-notifications');

    $response->assertStatus(422);
});

it('returns 422 when type is invalid', function (): void {
    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/organization-notifications?type=invalid_type');

    $response->assertStatus(422);
});

it('returns 422 when app user has no organization', function (): void {
    $appUserWithoutOrg = AppUser::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($appUserWithoutOrg)
        ->getJson('/api/app-user/organization-notifications?type=actuacion');

    $response->assertStatus(422);
});

it('returns notification_type and data and meta for actuacion list', function (): void {
    $process = Process::factory()->create(['process_number' => '76001333301320170009301']);
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Auto de apertura',
        'action_date' => now(),
        'registration_date' => now(),
    ]);

    $notification = OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/organization-notifications?type=actuacion');

    $response->assertStatus(200);
    $response->assertJsonPath('notification_type', 'actuacion');
    $response->assertJsonStructure([
        'data' => [
            [
                'notification_id',
                'notification_time_human',
                'detail' => [
                    'process_id',
                    'process_number',
                    'action',
                    'annotation',
                    'action_date',
                    'registration_date',
                    'term_start_date',
                    'term_end_date',
                    'subjects',
                ],
            ],
        ],
        'meta' => ['current_page', 'per_page', 'total', 'last_page'],
    ]);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.notification_id'))->toBe($notification->id);
    expect($response->json('data.0.detail.process_id'))->toBe($process->id);
    expect($response->json('data.0.detail.process_number'))->toBe('76001333301320170009301');
});

it('returns alert_highlights in detail for actuacion_alerta when action has highlights', function (): void {
    $process = Process::factory()->create(['process_number' => '76001333301320170009303']);
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'annotation' => 'Se abrió período de CONSULTA y se notifica APELACIÓN.',
        'action_date' => now(),
        'registration_date' => now(),
    ]);

    ProcessActionAlertHighlight::query()->create([
        'process_action_id' => $action->id,
        'start' => 21,
        'end' => 29,
        'detected_text' => 'CONSULTA',
    ]);
    ProcessActionAlertHighlight::query()->create([
        'process_action_id' => $action->id,
        'start' => 45,
        'end' => 54,
        'detected_text' => 'APELACIÓN',
    ]);

    OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion_alerta',
        'is_viewed' => false,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/organization-notifications?type=actuacion_alerta');

    $response->assertStatus(200);
    $response->assertJsonPath('notification_type', 'actuacion_alerta');

    $highlights = $response->json('data.0.detail.alert_highlights');
    expect($highlights)->toBeArray()->toHaveCount(2);
    expect($highlights[0])->toMatchArray(['start' => 21, 'end' => 29, 'text' => 'CONSULTA']);
    expect($highlights[1])->toMatchArray(['start' => 45, 'end' => 54, 'text' => 'APELACIÓN']);
});

it('filters actuacion_alerta by alert_slug and returns alert_type in highlights', function (): void {
    $keyword = \Src\Domain\Process\Models\AlertActionKeyword::query()->firstOrCreate(
        ['slug' => 'apelacion'],
        ['name' => 'Apelación']
    );
    $process = Process::factory()->create(['process_number' => '76001400301020180007600']);
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Auto de apelación',
        'annotation' => 'Se notifica APELACIÓN.',
        'action_date' => now(),
        'registration_date' => now(),
    ]);
    $action->alertActionKeywords()->attach($keyword->id);
    ProcessActionAlertHighlight::query()->create([
        'process_action_id' => $action->id,
        'start' => 12,
        'end' => 21,
        'detected_text' => 'APELACIÓN',
    ]);

    OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion_alerta',
        'is_viewed' => false,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/organization-notifications?type=actuacion_alerta&alert_slug=apelacion');

    $response->assertStatus(200);
    $response->assertJsonPath('notification_type', 'actuacion_alerta');
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.detail.alert_highlights.0.alert_type'))->toMatchArray([
        'id' => $keyword->id,
        'name' => 'Apelación',
        'slug' => 'apelacion',
    ]);
});

it('returns only unviewed notifications by default', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $actionViewed = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => now(),
        'registration_date' => now(),
    ]);
    $actionUnviewed = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => now(),
        'registration_date' => now(),
    ]);

    OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $actionViewed->id,
        'notifiable_type' => $actionViewed->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => true,
        'viewed_at' => now(),
    ]);
    OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $actionUnviewed->id,
        'notifiable_type' => $actionUnviewed->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/organization-notifications?type=actuacion');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.detail.action'))->toBe($actionUnviewed->action);
});

it('marks notifications as viewed and returns count', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => now(),
        'registration_date' => now(),
    ]);

    $notification = OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
    ]);

    $response = $this->actingAs($this->appUser)
        ->patchJson('/api/app-user/organization-notifications/mark-viewed', [
            'notification_ids' => [$notification->id],
        ]);

    $response->assertStatus(200);
    $response->assertJsonPath('marked', 1);

    $notification->refresh();
    expect($notification->is_viewed)->toBeTrue();
    expect($notification->viewed_at)->not->toBeNull();
});

it('mark viewed requires authentication', function (): void {
    $response = $this->patchJson('/api/app-user/organization-notifications/mark-viewed', [
        'notification_ids' => [(string) \Illuminate\Support\Str::uuid()],
    ]);

    $response->assertStatus(401);
});

it('mark viewed returns 422 when app user has no organization', function (): void {
    $appUserWithoutOrg = AppUser::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($appUserWithoutOrg)
        ->patchJson('/api/app-user/organization-notifications/mark-viewed', [
            'notification_ids' => [(string) \Illuminate\Support\Str::uuid()],
        ]);

    $response->assertStatus(422);
});

it('mark viewed only updates notifications belonging to user organization', function (): void {
    $otherOrg = Organization::factory()->create();
    $process = Process::factory()->create();
    $process->organizations()->attach($otherOrg->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => now(),
        'registration_date' => now(),
    ]);

    $otherNotification = OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $otherOrg->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
    ]);

    $response = $this->actingAs($this->appUser)
        ->patchJson('/api/app-user/organization-notifications/mark-viewed', [
            'notification_ids' => [$otherNotification->id],
        ]);

    $response->assertStatus(200);
    $response->assertJsonPath('marked', 0);

    $otherNotification->refresh();
    expect($otherNotification->is_viewed)->toBeFalse();
});

it('mark all viewed returns 422 when type is invalid', function (): void {
    $response = $this->actingAs($this->appUser)
        ->patchJson('/api/app-user/organization-notifications/mark-all-viewed?type=invalid');

    $response->assertStatus(422);
});

it('mark all viewed requires authentication', function (): void {
    $response = $this->patchJson('/api/app-user/organization-notifications/mark-all-viewed');

    $response->assertStatus(401);
});

it('mark all viewed returns 422 when app user has no organization', function (): void {
    $appUserWithoutOrg = AppUser::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($appUserWithoutOrg)
        ->patchJson('/api/app-user/organization-notifications/mark-all-viewed');

    $response->assertStatus(422);
});

it('mark all viewed marks all unviewed notifications of the organization', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => now(),
        'registration_date' => now(),
    ]);

    $n1 = OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
    ]);
    $n2 = OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion_alerta',
        'is_viewed' => false,
    ]);

    $response = $this->actingAs($this->appUser)
        ->patchJson('/api/app-user/organization-notifications/mark-all-viewed');

    $response->assertStatus(200);
    $response->assertJsonPath('marked', 2);

    $n1->refresh();
    $n2->refresh();
    expect($n1->is_viewed)->toBeTrue()
        ->and($n2->is_viewed)->toBeTrue();
});

it('mark all viewed with type filters by notification type', function (): void {
    $isolatedOrg = Organization::factory()->create();
    $isolatedUser = AppUser::factory()->create(['email_verified_at' => now()]);
    $isolatedUser->organizations()->attach($isolatedOrg->id, ['is_owner' => true]);

    $process = Process::factory()->create();
    $process->organizations()->attach($isolatedOrg->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => now(),
        'registration_date' => now(),
    ]);

    $actuacionNotif = OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $isolatedOrg->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
    ]);
    $alertaNotif = OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $isolatedOrg->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion_alerta',
        'is_viewed' => false,
    ]);

    $actuacionId = $actuacionNotif->id;
    $alertaId = $alertaNotif->id;

    $response = $this->actingAs($isolatedUser)
        ->patchJson('/api/app-user/organization-notifications/mark-all-viewed?type=actuacion');

    $response->assertStatus(200);
    $response->assertJsonPath('marked', 1);

    $actuacionAfter = OrganizationNotification::query()->where('id', $actuacionId)->first();
    $alertaAfter = OrganizationNotification::query()->where('id', $alertaId)->first();
    expect($actuacionAfter->is_viewed)->toBeTrue()
        ->and($alertaAfter->is_viewed)->toBeFalse();
});

it('excludes historical actuacion notifications already covered by a sent digest', function (): void {
    $process = Process::factory()->create(['process_number' => '76001310301820210024600']);
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $digest = \Src\Domain\Notification\Models\NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'email_sent_at' => now(),
    ]);

    $sentAction = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => '2026-06-01',
        'action_date' => '2026-06-01',
    ]);

    OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $sentAction->id,
        'notifiable_type' => $sentAction->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => true,
        'is_email_notified' => true,
        'notification_digest_id' => $digest->id,
    ]);

    $historicalAction = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Recepción memorial',
        'registration_date' => '2020-05-10',
        'action_date' => '2020-05-10',
    ]);

    $currentAction = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Incorpora memorial en despacho',
        'registration_date' => '2026-06-10',
        'action_date' => '2026-06-10',
    ]);

    OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $historicalAction->id,
        'notifiable_type' => $historicalAction->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
    ]);

    $currentNotificationId = (string) \Illuminate\Support\Str::uuid();

    OrganizationNotification::create([
        'id' => $currentNotificationId,
        'organization_id' => $this->organization->id,
        'notifiable_id' => $currentAction->id,
        'notifiable_type' => $currentAction->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/organization-notifications?type=actuacion');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.notification_id'))->toBe($currentNotificationId);
    expect($response->json('data.0.detail.action'))->toBe('Incorpora memorial en despacho');
});

<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\Notification\Controllers;

use Illuminate\Support\Facades\Date;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Organization\Models\Organization;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create();
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);

    actingAs($this->appUser, 'sanctum');
});

it('can list notification digest history with correct labels and periods', function (string $time, string $expectedPeriodEs, string $expectedPeriodEn) {
    // We'll test with different times of the day
    Date::setTestNow("2026-04-18 $time");

    NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'created_at' => now(),
    ]);

    // Test Spanish
    app()->setLocale('es');
    $response = getJson('/api/app-user/notification-digests/history');

    $response->assertOk()
        ->assertJsonCount(1, 'data');

    $data = $response->json('data.0');
    expect($data['date'])->toBe('18 de abril de 2026');
    expect($data['time'])->toBe(now()->format('g:ia'));
    expect($data['period'])->toBe($expectedPeriodEs);
    expect($data['actions_count'])->toBe(1); // Factory creates 1 action by default
    expect($data['is_notified'])->toBeTrue();
    expect($data['email_notified_at'])->not->toBeNull();
    expect($data['whatsapp_notified_at'])->toBeNull();
    expect($data['sms_notified_at'])->toBeNull();

    // Test English
    app()->setLocale('en');
    $responseEn = getJson('/api/app-user/notification-digests/history');
    $dataEn = $responseEn->json('data.0');
    expect($dataEn['date'])->toBe('April 18, 2026');
    expect($dataEn['time'])->toBe(now()->format('g:ia'));
    expect($dataEn['period'])->toBe($expectedPeriodEn);
})->with([
    ['08:00:00', 'Mañana', 'Morning'],
    ['14:00:00', 'Tarde', 'Afternoon'],
    ['20:00:00', 'Noche', 'Night'],
]);

it('only returns digests for the users organization', function () {
    $otherOrg = Organization::factory()->create();

    NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'created_at' => now(),
    ]);

    NotificationDigest::factory()->create([
        'organization_id' => $otherOrg->id,
        'created_at' => now(),
    ]);

    $response = getJson('/api/app-user/notification-digests/history');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

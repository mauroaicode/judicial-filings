<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create();
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);

    actingAs($this->appUser, 'sanctum');
    $this->freezeTime();
});

it('can list notification digests for the organization', function () {
    NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'created_at' => now(),
    ]);

    NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'created_at' => now()->subDay(),
    ]);

    NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'created_at' => now()->subDays(2),
    ]);

    // Otro de otra organización
    NotificationDigest::factory()->create();

    // Solicitamos explícitamente un rango amplio para evitar el filtro por defecto de "solo hoy"
    $response = getJson('/api/app-user/notification-digests?created_at_from='.now()->subDays(5)->format('Y-m-d').'&created_at_to='.now()->format('Y-m-d'));

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('can filter notification digests by created_at date range', function () {
    NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'created_at' => '2026-03-10 10:00:00',
    ]);

    NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'created_at' => '2026-03-20 10:00:00',
    ]);

    $response = getJson('/api/app-user/notification-digests?created_at_from=2026-03-15&created_at_to=2026-03-25');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('can filter notification digests by process number (radicado)', function () {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, ['interest_date' => now()]);

    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
    ]);

    $digest = NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'data' => [
            ['process_number' => $process->process_number, 'action_text' => 'Movimiento detectado'],
        ],
    ]);

    // Crear la notificación vinculada para que el filtro relacional funcione
    DB::table('organization_notifications')->insert([
        'id' => fake()->uuid(),
        'organization_id' => $this->organization->id,
        'notification_digest_id' => $digest->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => ProcessAction::class,
        'notification_type' => 'judicial_action_detected',
        'is_viewed' => false,
        'is_notified' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Otro digest sin ese proceso
    NotificationDigest::factory()->create(['organization_id' => $this->organization->id]);

    $response = getJson('/api/app-user/notification-digests?process_number='.$process->process_number);

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('can filter notification digests by action date', function () {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, ['interest_date' => now()]);

    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => '2026-03-19',
    ]);

    $digest = NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    DB::table('organization_notifications')->insert([
        'id' => fake()->uuid(),
        'organization_id' => $this->organization->id,
        'notification_digest_id' => $digest->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => ProcessAction::class,
        'notification_type' => 'judicial_action_detected',
        'is_viewed' => false,
        'is_notified' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = getJson('/api/app-user/notification-digests?action_date_from=2026-03-18&action_date_to=2026-03-20');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

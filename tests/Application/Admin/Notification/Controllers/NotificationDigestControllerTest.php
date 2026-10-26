<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin-digest-list@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);
    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organizationA = Organization::factory()->create(['name' => 'Bufete Alfa']);
    $this->organizationB = Organization::factory()->create(['name' => 'Bufete Beta']);

    $this->unviewed = NotificationDigest::factory()->create([
        'organization_id' => $this->organizationA->id,
        'created_at' => '2030-01-15 08:00:00',
        'viewed_at' => null,
    ]);

    $this->viewed = NotificationDigest::factory()->create([
        'organization_id' => $this->organizationB->id,
        'created_at' => '2030-01-15 20:00:00',
        'viewed_at' => '2030-01-16 09:30:00',
    ]);
});

it('requires authentication to list notification digests', function (): void {
    $this->getJson('/api/admin/notification-digests')->assertUnauthorized();
});

it('forbids non-admin users from listing notification digests', function (): void {
    $plain = User::factory()->create([
        'email' => 'plain-digest-list@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $this->actingAs($plain)
        ->getJson('/api/admin/notification-digests')
        ->assertForbidden();
});

it('lists digests from every organization with the same history fields and viewed state', function (): void {
    app()->setLocale('es');

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/notification-digests?created_at_from=2030-01-15&created_at_to=2030-01-15');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonFragment([
            'id' => $this->unviewed->id,
            'organization_id' => $this->organizationA->id,
            'organization_name' => 'Bufete Alfa',
            'date' => '15 de enero de 2030',
            'time' => '8:00am',
            'period' => 'Mañana',
            'actions_count' => 1,
            'is_notified' => true,
            'is_viewed' => false,
            'viewed_at' => null,
        ])
        ->assertJsonFragment([
            'id' => $this->viewed->id,
            'organization_id' => $this->organizationB->id,
            'organization_name' => 'Bufete Beta',
            'time' => '8:00pm',
            'period' => 'Noche',
            'is_viewed' => true,
        ]);

    expect($response->json('data.0.viewed_at'))->not->toBeNull();
});

it('filters notification digests by organization', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/notification-digests?organization_id='.$this->organizationA->id.'&created_at_from=2030-01-15&created_at_to=2030-01-15');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->unviewed->id)
        ->assertJsonPath('data.0.organization_id', $this->organizationA->id);
});

it('filters notification digests by process number', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organizationA->id, ['interest_date' => now()]);

    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
    ]);

    $matching = NotificationDigest::factory()->create([
        'organization_id' => $this->organizationA->id,
        'created_at' => '2030-02-01 10:00:00',
        'data' => [
            ['process_number' => $process->process_number, 'action_text' => 'Movimiento detectado'],
        ],
    ]);

    \Illuminate\Support\Facades\DB::table('organization_notifications')->insert([
        'id' => fake()->uuid(),
        'organization_id' => $this->organizationA->id,
        'notification_digest_id' => $matching->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => ProcessAction::class,
        'notification_type' => 'judicial_action_detected',
        'is_viewed' => false,
        'is_notified' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    NotificationDigest::factory()->create([
        'organization_id' => $this->organizationB->id,
        'created_at' => '2030-02-01 11:00:00',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/notification-digests?process_number='.$process->process_number.'&created_at_from=2030-02-01&created_at_to=2030-02-01');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id);
});

it('orders the admin digest list without sorting the json payload', function (): void {
    $statements = [];

    \Illuminate\Support\Facades\DB::listen(function ($query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    $this->actingAs($this->user)
        ->getJson('/api/admin/notification-digests')
        ->assertOk();

    $ordered = collect($statements)->filter(
        fn (string $sql): bool => str_contains(strtolower($sql), 'order by')
            && str_contains($sql, 'notification_digests'),
    );

    expect($ordered)->not->toBeEmpty();

    $ordered->each(function (string $sql): void {
        expect(strtolower($sql))->not->toContain('`data`')
            ->and(strtolower($sql))->not->toContain('"data"')
            ->and(strtolower($sql))->not->toContain(' data');
    });
});

it('rejects an unknown organization filter', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/admin/notification-digests?organization_id=00000000-0000-4000-8000-000000000000')
        ->assertUnprocessable();
});

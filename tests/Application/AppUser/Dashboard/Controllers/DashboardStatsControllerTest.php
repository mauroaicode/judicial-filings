<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create([
        'email' => 'dashboard@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);
});

it('requires authentication for dashboard stats', function (): void {
    $response = $this->getJson('/api/app-user/dashboard/stats');

    $response->assertStatus(401);
});

it('returns 422 when app user has no organization', function (): void {
    $appUserWithoutOrg = AppUser::factory()->create([
        'email' => 'noorg@example.com',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($appUserWithoutOrg)
        ->getJson('/api/app-user/dashboard/stats');

    $response->assertStatus(422);
});

it('returns zero counts when organization has no processes nor notifications', function (): void {
    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('total_processes', 0);
    $response->assertJsonPath('active_processes', 0);
    $response->assertJsonPath('inactive_processes', 0);
    $response->assertJsonPath('notifications.by_type.actuacion', 0);
    $response->assertJsonPath('notifications.by_type.actuacion_alerta', 0);
});

it('returns correct process counts for organization', function (): void {
    $process1 = Process::factory()->create();
    $process2 = Process::factory()->create();
    $process3 = Process::factory()->create();

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process3->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('total_processes', 3);
    $response->assertJsonPath('active_processes', 2);
    $response->assertJsonPath('inactive_processes', 1);
});

it('returns correct notification counts by type', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $action = ProcessAction::factory()->create(['process_id' => $process->id]);

    OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
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
        ->getJson('/api/app-user/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('notifications.by_type.actuacion', 1);
    $response->assertJsonPath('notifications.by_type.actuacion_alerta', 1);
});

it('accepts lawyer_role none filter', function (): void {
    $withRole = Process::factory()->create();
    $withoutRole = Process::factory()->create();

    $withRole->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'lawyer_role' => 'plaintiff',
    ]);
    $withoutRole->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'lawyer_role' => null,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/dashboard/stats?lawyer_role=none');

    $response->assertStatus(200);
    $response->assertJsonPath('total_processes', 1);
});

it('counts simulated green for plaintiff with recent activity when filtering severity_color green', function (): void {
    $this->freezeTime();

    $movingPlaintiff = Process::factory()->create(['last_activity_date' => now()->subDays(5)]);
    $oldPlaintiff = Process::factory()->create(['last_activity_date' => now()->subDays(40)]);

    $movingPlaintiff->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'lawyer_role' => 'plaintiff',
        'inactivity_alert_level' => null,
    ]);
    $oldPlaintiff->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'lawyer_role' => 'plaintiff',
        'inactivity_alert_level' => null,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/dashboard/stats?severity_color=green');

    $response->assertStatus(200);
    $response->assertJsonPath('total_processes', 1);
});

it('accepts severity_color none filter', function (): void {
    $waiting = Process::factory()->create(['last_activity_date' => null]);
    $withColor = Process::factory()->create(['last_activity_date' => now()]);

    $waiting->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'inactivity_alert_level' => null,
    ]);
    $withColor->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'inactivity_alert_level' => 'red',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/dashboard/stats?severity_color=none');

    $response->assertStatus(200);
    $response->assertJsonPath('total_processes', 1);
});

it('excludes viewed notifications from counts', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $action = ProcessAction::factory()->create(['process_id' => $process->id]);

    OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => true,
        'viewed_at' => now(),
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('notifications.by_type.actuacion', 0);
});

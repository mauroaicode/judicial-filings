<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    Process::query()->delete();

    $this->user = User::factory()->create([
        'email' => 'admin@dashboard.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create();
});

it('requires authentication to access admin dashboard stats', function (): void {
    $response = $this->getJson('/api/admin/dashboard/stats');

    $response->assertStatus(401);
});

it('returns zero counts when there are no processes', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('total_processes', 0);
    $response->assertJsonPath('active_processes', 0);
    $response->assertJsonPath('orphan_processes', 0);
    $response->assertJsonPath('private_processes', 0);
    $response->assertJsonPath('processes_with_multiple_instances', 0);
    $response->assertJsonPath('outdated_processes', 0);
    $response->assertJsonPath('critical_alert_processes', 0);
    $response->assertJsonPath('early_attention_processes', 0);
});

it('counts orphan_processes from processes.status ignoring organization pivot', function (): void {
    $process = Process::factory()->create(['status' => 'inactivo']);
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('total_processes', 1);
    $response->assertJsonPath('active_processes', 0);
    $response->assertJsonPath('orphan_processes', 1);
});

it('counts distinct private process_numbers where is_private is true', function (): void {
    Process::factory()->create(['process_number' => '11001418901234567890123', 'is_private' => true]);
    Process::factory()->create(['process_number' => '22001418901234567890123', 'is_private' => true]);
    Process::factory()->create(['process_number' => '33001418901234567890123', 'is_private' => false]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('private_processes', 2);
    $response->assertJsonPath('total_processes', 3);
});

it('counts active_processes using processes.status not pivot is_active', function (): void {
    $processActive = Process::factory()->create(['status' => 'activo']);
    $processPivotInactiveOnly = Process::factory()->create(['status' => 'activo']);

    $processPivotInactiveOnly->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('total_processes', 2);
    $response->assertJsonPath('active_processes', 2);
    $response->assertJsonPath('orphan_processes', 0);
});

it('returns correct total active and orphan counts using judicial status on processes table', function (): void {
    Process::factory()->create(['status' => 'activo']);
    Process::factory()->create(['status' => 'activo']);
    Process::factory()->create(['status' => 'inactivo']);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('total_processes', 3);
    $response->assertJsonPath('active_processes', 2);
    $response->assertJsonPath('orphan_processes', 1);
});

it('returns correct count of processes with multiple instances', function (): void {
    $withMultiple = Process::factory()->count(4)->create(['has_multiple_instances' => true]);
    $withoutMultiple = Process::factory()->count(3)->create(['has_multiple_instances' => false]);

    foreach ($withMultiple as $process) {
        $process->organizations()->attach($this->organization->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
        ]);
    }

    foreach ($withoutMultiple as $process) {
        $process->organizations()->attach($this->organization->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
        ]);
    }

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('processes_with_multiple_instances', 4);
    $response->assertJsonPath('total_processes', 7);
});

it('counts outdated processes with null last_api_update', function (): void {
    $outdated = Process::factory()->create(['last_api_update' => null]);
    $synced = Process::factory()->create(['last_api_update' => now()]);

    $outdated->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $synced->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('outdated_processes', 1);
});

it('counts outdated processes with last_api_update older than 48 hours', function (): void {
    $outdated = Process::factory()->create(['last_api_update' => now()->subHours(49)]);
    $recent = Process::factory()->create(['last_api_update' => now()->subHours(24)]);

    $outdated->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $recent->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('outdated_processes', 1);
});

it('does not count recently synced processes as outdated', function (): void {
    $recentlySynced = Process::factory()->create(['last_api_update' => now()->subHours(1)]);

    $recentlySynced->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('outdated_processes', 0);
});

it('counts processes with red inactivity alert level as critical', function (): void {
    $critical = Process::factory()->create();
    $warning = Process::factory()->create();
    $normal = Process::factory()->create();

    $critical->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'inactivity_alert_level' => 'red',
        'lawyer_role' => 'plaintiff',
    ]);

    $warning->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'inactivity_alert_level' => 'yellow',
        'lawyer_role' => 'plaintiff',
    ]);

    $normal->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'inactivity_alert_level' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('critical_alert_processes', 1);
    $response->assertJsonPath('early_attention_processes', 1);
});

it('counts critical processes across all organizations', function (): void {
    $org2 = Organization::factory()->create();

    $critical1 = Process::factory()->create();
    $critical2 = Process::factory()->create();
    $notCritical = Process::factory()->create();

    $critical1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'inactivity_alert_level' => 'red',
        'lawyer_role' => 'plaintiff',
    ]);

    $critical2->organizations()->attach($org2->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'inactivity_alert_level' => 'red',
        'lawyer_role' => 'plaintiff',
    ]);

    $notCritical->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'inactivity_alert_level' => 'green',
        'lawyer_role' => 'defendant',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('critical_alert_processes', 2);
    $response->assertJsonPath('early_attention_processes', 0);
});

it('counts processes with yellow inactivity alert level as early attention', function (): void {
    $earlyAttention = Process::factory()->create();
    $critical = Process::factory()->create();
    $normal = Process::factory()->create();

    $earlyAttention->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'inactivity_alert_level' => 'yellow',
        'lawyer_role' => 'plaintiff',
    ]);

    $critical->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'inactivity_alert_level' => 'red',
        'lawyer_role' => 'plaintiff',
    ]);

    $normal->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'inactivity_alert_level' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('early_attention_processes', 1);
    $response->assertJsonPath('critical_alert_processes', 1);
});

it('returns correct json structure', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'total_processes',
        'active_processes',
        'orphan_processes',
        'private_processes',
        'processes_with_multiple_instances',
        'outdated_processes',
        'critical_alert_processes',
        'early_attention_processes',
    ]);
});

it('counts process linked to multiple organizations only once in total', function (): void {
    $org2 = Organization::factory()->create();

    $sharedProcess = Process::factory()->create();

    $sharedProcess->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $sharedProcess->organizations()->attach($org2->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/dashboard/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('total_processes', 1);
});

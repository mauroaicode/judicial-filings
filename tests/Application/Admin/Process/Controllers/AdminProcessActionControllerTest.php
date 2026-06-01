<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\AlertActionKeyword;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin-actions@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);
});

it('requires authentication to list admin process actions', function (): void {
    $process = Process::factory()->create();

    $response = $this->getJson("/api/admin/processes/{$process->id}/actions");

    $response->assertStatus(401);
});

it('returns empty list when process has no actions (admin)', function (): void {
    $process = Process::factory()->create();

    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/processes/{$process->id}/actions");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data',
        'current_page',
        'per_page',
        'total',
    ]);
    expect($response->json('data'))->toBeEmpty();
});

it('returns process actions for a process (admin)', function (): void {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create();
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $action1 = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_registration_id' => 123456,
        'cons_action' => 1,
        'action_date' => '2024-01-15',
        'action' => 'Test action 1',
        'annotation' => 'Test annotation 1',
        'registration_date' => '2024-01-15',
    ]);

    $action2 = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_registration_id' => 123457,
        'cons_action' => 2,
        'action_date' => '2024-01-20',
        'action' => 'Test action 2',
        'annotation' => 'Test annotation 2',
        'registration_date' => '2024-01-20',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/processes/{$process->id}/actions");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('total'))->toBe(2);
    expect($response->json('data.0.id'))->toBe($action2->id); // Ordered by cons_action desc
    expect($response->json('data.1.id'))->toBe($action1->id);
    expect($response->json('data.0.index'))->toBe(1);
    expect($response->json('data.1.index'))->toBe(2);
});

it('filters actions by action_date_from (admin)', function (): void {
    $process = Process::factory()->create();

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => '2024-01-10',
        'cons_action' => 1,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => '2024-01-20',
        'cons_action' => 2,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => '2024-02-01',
        'cons_action' => 3,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/processes/{$process->id}/actions?action_date_from=2024-01-15");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('returns paginated results (admin)', function (): void {
    $process = Process::factory()->create();

    ProcessAction::factory()->count(25)->create([
        'process_id' => $process->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/processes/{$process->id}/actions?per_page=10");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(10);
    expect($response->json('per_page'))->toBe(10);
    expect($response->json('total'))->toBe(25);
    expect($response->json('last_page'))->toBe(3);
});

it('returns alert keywords for process (admin)', function (): void {
    $process = Process::factory()->create();

    $slug = 'sentencia-'.fake()->uuid();
    $keyword = AlertActionKeyword::query()->create([
        'name' => 'Sentencia',
        'slug' => $slug,
    ]);

    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
    ]);

    // attach keyword to action
    \Illuminate\Support\Facades\DB::table('process_action_alert_action_keyword')->insert([
        'process_action_id' => $action->id,
        'alert_action_keyword_id' => $keyword->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/processes/{$process->id}/alert-keywords");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.slug'))->toBe($slug);
});

it('returns alert keyword stats for process (admin)', function (): void {
    $process = Process::factory()->create();

    $slug = 'sentencia-'.fake()->uuid();
    $keyword = AlertActionKeyword::query()->create([
        'name' => 'Sentencia',
        'slug' => $slug,
    ]);

    $action1 = ProcessAction::factory()->create(['process_id' => $process->id]);
    $action2 = ProcessAction::factory()->create(['process_id' => $process->id]);

    \Illuminate\Support\Facades\DB::table('process_action_alert_action_keyword')->insert([
        ['process_action_id' => $action1->id, 'alert_action_keyword_id' => $keyword->id],
        ['process_action_id' => $action2->id, 'alert_action_keyword_id' => $keyword->id],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/processes/{$process->id}/alert-keyword-stats");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.slug'))->toBe($slug);
    expect($response->json('data.0.count'))->toBe(2);
});

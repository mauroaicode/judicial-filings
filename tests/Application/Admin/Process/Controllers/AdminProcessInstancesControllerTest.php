<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin-instances@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);
});

it('requires authentication to list admin process instances', function (): void {
    $process = Process::factory()->create();

    $response = $this->getJson("/api/admin/processes/{$process->id}/instances");

    $response->assertStatus(401);
});

it('returns 404 when process does not exist (admin instances)', function (): void {
    $missingId = '00000000-0000-0000-0000-000000000000';

    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/processes/{$missingId}/instances");

    $response->assertStatus(404);
});

it('returns all instances for a multi-instance process (admin)', function (): void {
    $processNumber = '11001400303520160089003';

    $instance1 = Process::factory()->create([
        'process_number' => $processNumber,
        'court' => 'JUZGADO 006 CIVIL MUNICIPAL DE EJECUCION',
        'last_activity_date' => now()->subDays(1),
        'has_multiple_instances' => true,
    ]);
    $instance2 = Process::factory()->create([
        'process_number' => $processNumber,
        'court' => 'JUZGADO 010 CIVIL MUNICIPAL DE CALI',
        'last_activity_date' => now()->subDays(30),
        'has_multiple_instances' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/processes/{$instance1->id}/instances");

    $response->assertStatus(200);
    $response->assertJsonCount(2);

    $ids = collect($response->json())->pluck('id')->all();
    expect($ids)->toContain($instance1->id)
        ->and($ids)->toContain($instance2->id);
});

it('returns correct actions_count per instance (admin)', function (): void {
    $processNumber = '76001333301320200001234';

    $instance1 = Process::factory()->create([
        'process_number' => $processNumber,
        'has_multiple_instances' => true,
    ]);
    $instance2 = Process::factory()->create([
        'process_number' => $processNumber,
        'has_multiple_instances' => true,
    ]);

    ProcessAction::factory()->count(5)->create(['process_id' => $instance1->id]);
    ProcessAction::factory()->count(2)->create(['process_id' => $instance2->id]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/processes/{$instance1->id}/instances");

    $response->assertStatus(200);

    $data = collect($response->json());
    expect($data->firstWhere('id', $instance1->id)['actions_count'])->toBe(5);
    expect($data->firstWhere('id', $instance2->id)['actions_count'])->toBe(2);
});

it('orders instances by last_activity_date descending (admin)', function (): void {
    $processNumber = '76001333301320200009876';

    $oldest = Process::factory()->create([
        'process_number' => $processNumber,
        'last_activity_date' => now()->subDays(100),
        'has_multiple_instances' => true,
    ]);
    $newest = Process::factory()->create([
        'process_number' => $processNumber,
        'last_activity_date' => now()->subDays(1),
        'has_multiple_instances' => true,
    ]);
    $middle = Process::factory()->create([
        'process_number' => $processNumber,
        'last_activity_date' => now()->subDays(50),
        'has_multiple_instances' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/processes/{$oldest->id}/instances");

    $response->assertStatus(200);

    $ids = collect($response->json())->pluck('id')->all();
    expect($ids[0])->toBe($newest->id)
        ->and($ids[1])->toBe($middle->id)
        ->and($ids[2])->toBe($oldest->id);
});

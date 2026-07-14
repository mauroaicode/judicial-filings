<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Enums\TaskType;
use Src\Domain\Task\Models\Task;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->process = Process::factory()->create();

    $this->organization->processes()->attach($this->process->id, [
        'is_active' => true,
        'interest_date' => now(),
        'status' => 'active',
    ]);

    $this->user = AppUser::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->user->organizations()->attach($this->organization->id, ['is_owner' => true]);
});

it('requires authentication to list process tasks', function (): void {
    $this->getJson("/api/app-user/processes/{$this->process->id}/tasks")
        ->assertUnauthorized();
});

it('lists tasks for the process belonging to the user organization', function (): void {
    Task::factory()->count(2)->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'is_admin' => false,
    ]);

    Task::factory()->create([
        'organization_id' => $this->organization->id,
        'process_id' => Process::factory()->create()->id,
        'is_admin' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/app-user/processes/{$this->process->id}/tasks");

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonPath('data.0.process_id', $this->process->id);
    $response->assertJsonPath('data.1.process_id', $this->process->id);
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'title',
                'type',
                'type_label',
                'status',
                'urgency_level',
                'days_overdue',
                'process_id',
                'organization_id',
            ],
        ],
    ]);
});

it('does not list tasks from another organization sharing the same process', function (): void {
    $otherOrganization = Organization::factory()->create();
    $otherOrganization->processes()->attach($this->process->id, [
        'is_active' => true,
        'interest_date' => now(),
        'status' => 'active',
    ]);

    Task::factory()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'is_admin' => false,
        'title' => 'Own org task',
    ]);

    Task::factory()->create([
        'organization_id' => $otherOrganization->id,
        'process_id' => $this->process->id,
        'is_admin' => false,
        'title' => 'Other org task',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/app-user/processes/{$this->process->id}/tasks");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.title', 'Own org task');
    $response->assertJsonPath('data.0.organization_id', $this->organization->id);
});

it('filters process tasks by status query param', function (): void {
    Task::factory()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'status' => TaskStatus::PENDING,
        'is_admin' => false,
    ]);
    Task::factory()->completed()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'is_admin' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/app-user/processes/{$this->process->id}/tasks?status=completed");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.status', 'completed');
});

it('filters process tasks by type query param', function (): void {
    Task::factory()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'type' => TaskType::GENERAL,
        'is_admin' => false,
    ]);
    Task::factory()->suspension()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'is_admin' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/app-user/processes/{$this->process->id}/tasks?type=suspension");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.type', 'suspension');
});

it('rejects invalid status query param', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson("/api/app-user/processes/{$this->process->id}/tasks?status=invalid");

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['status']);
});

it('returns 404 when process does not belong to the user organization', function (): void {
    $foreignProcess = Process::factory()->create();

    $response = $this->actingAs($this->user)
        ->getJson("/api/app-user/processes/{$foreignProcess->id}/tasks");

    $response->assertNotFound();
});

it('does not include admin tasks in process task listing', function (): void {
    Task::factory()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'is_admin' => false,
        'title' => 'App user task',
    ]);
    Task::factory()->admin()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'title' => 'Admin task',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/app-user/processes/{$this->process->id}/tasks");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.title', 'App user task');
});

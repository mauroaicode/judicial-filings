<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Task\Models\Task;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->process = Process::factory()->create();

    // Relate process and organization to pass business logic validation
    $this->organization->processes()->attach($this->process->id, [
        'is_active' => true,
        'interest_date' => now(),
    ]);

    $this->user = AppUser::factory()->create([
        'password' => Hash::make('password'),
    ]);
});

it('can list tasks', function (): void {
    Task::factory()->count(3)->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/app-user/tasks?organization_id={$this->organization->id}");

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'data');
});

it('can create a task', function (): void {
    $data = [
        'title' => 'Test Task',
        'description' => 'Test Description',
        'is_admin' => false,
        'process_id' => $this->process->id,
        'organization_id' => $this->organization->id,
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/app-user/tasks', $data);

    $response->assertStatus(201);
    $this->assertDatabaseHas('tasks', [
        'title' => 'Test Task',
        'organization_id' => $this->organization->id,
    ]);
    $response->assertJsonPath('title', 'Test Task');
    $response->assertJsonPath('process_number', $this->process->process_number);
});

it('fails to create a task if process does not belong to organization', function (): void {
    $anotherProcess = Process::factory()->create(); // Not related to $this->organization

    $data = [
        'title' => 'Test Task',
        'description' => 'Test Description',
        'is_admin' => false,
        'process_id' => $anotherProcess->id,
        'organization_id' => $this->organization->id,
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/app-user/tasks', $data);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['process_id']);
});

it('can show a task', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/app-user/tasks/{$task->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('title', $task->title);
    $response->assertJsonPath('id', $task->id);
});

it('can update a task', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $data = [
        'title' => 'Updated Title',
        'description' => 'Updated Description',
        'is_admin' => true,
        'process_id' => $this->process->id,
        'organization_id' => $this->organization->id,
    ];

    $response = $this->actingAs($this->user)
        ->putJson("/api/app-user/tasks/{$task->id}", $data);

    $response->assertStatus(200);
    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'title' => 'Updated Title',
        'is_admin' => true,
    ]);
    $response->assertJsonPath('title', 'Updated Title');
});

it('fails to update a task if organization does not exist', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $data = [
        'title' => 'Updated Title',
        'description' => 'Updated Description',
        'is_admin' => true,
        'process_id' => null,
        'organization_id' => (string) \Illuminate\Support\Str::uuid(), // Non-existent
    ];

    $response = $this->actingAs($this->user)
        ->putJson("/api/app-user/tasks/{$task->id}", $data);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['organization_id']);
});

it('can delete a task', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/app-user/tasks/{$task->id}");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});

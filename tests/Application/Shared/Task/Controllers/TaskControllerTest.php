<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Role\Models\Role;
use Src\Domain\Task\Models\Task;
use Src\Domain\User\Models\User;

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

    // Relate user and organization
    $this->user->organizations()->attach($this->organization->id, ['is_owner' => true]);
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
        'due_date' => '2026-07-15',
        'reminder_days' => 3,
        'is_admin' => false,
        'process_id' => $this->process->id,
        'organization_id' => $this->organization->id,
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/app-user/tasks', $data);

    $response->assertStatus(201);
    $this->assertDatabaseHas('tasks', [
        'title' => 'Test Task',
        'due_date' => '2026-07-15',
        'reminder_days' => 3,
        'organization_id' => $this->organization->id,
    ]);
    $response->assertJsonPath('title', 'Test Task');
    $response->assertJsonPath('due_date', '2026-07-15');
    $response->assertJsonPath('reminder_days', 3);
    $response->assertJsonPath('status', 'pending');
    $response->assertJsonPath('status_label', __('enums.task_status.pending'));
    $response->assertJsonPath('process_number', $this->process->process_number);
});

it('excludes completed tasks from the default list', function (): void {
    Task::factory()->count(2)->create([
        'organization_id' => $this->organization->id,
    ]);
    Task::factory()->completed()->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/app-user/tasks?organization_id={$this->organization->id}");

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'data');
});

it('can list completed tasks when filtering by status', function (): void {
    Task::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    Task::factory()->completed()->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/app-user/tasks?organization_id={$this->organization->id}&status=completed");

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.status', 'completed');
});

it('can mark a task as completed', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/api/app-user/tasks/{$task->id}/complete");

    $response->assertStatus(200);
    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'status' => 'completed',
    ]);
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('status_label', __('enums.task_status.completed'));
});

it('can change task status', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/api/app-user/tasks/{$task->id}/status", [
            'status' => 'draft',
        ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'status' => 'draft',
    ]);
    $response->assertJsonPath('status', 'draft');
    $response->assertJsonPath('status_label', __('enums.task_status.draft'));
});

it('can reopen a completed task', function (): void {
    $task = Task::factory()->completed()->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/api/app-user/tasks/{$task->id}/status", [
            'status' => 'pending',
        ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'status' => 'pending',
    ]);
    $response->assertJsonPath('status', 'pending');
});

it('fails to change task status with invalid value', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/api/app-user/tasks/{$task->id}/status", [
            'status' => 'invalid',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['status']);
});

it('fails to create a task if process does not belong to organization', function (): void {
    $anotherProcess = Process::factory()->create(); // Not related to $this->organization

    $data = [
        'title' => 'Test Task',
        'description' => 'Test Description',
        'due_date' => '2026-07-15',
        'reminder_days' => 1,
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
        'due_date' => '2026-08-01',
        'reminder_days' => 5,
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
        'due_date' => '2026-08-01',
        'reminder_days' => 5,
        'is_admin' => false,
    ]);
    $response->assertJsonPath('title', 'Updated Title');
    $response->assertJsonPath('due_date', '2026-08-01');
    $response->assertJsonPath('reminder_days', 5);
    $response->assertJsonPath('is_admin', false);
});

it('fails to update a task if organization does not exist', function (): void {
    $admin = User::factory()->create(['password' => 'password']);

    // Relate admin user and role
    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $admin->roles()->attach($adminRole->id);

    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $data = [
        'title' => 'Updated Title',
        'description' => 'Updated Description',
        'due_date' => '2026-08-01',
        'reminder_days' => 2,
        'is_admin' => true,
        'process_id' => null,
        'organization_id' => (string) \Illuminate\Support\Str::uuid(), // Non-existent
    ];

    $response = $this->actingAs($admin)
        ->putJson("/api/admin/tasks/{$task->id}", $data);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['organization_id']);
});

it('can move a task to trash', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/app-user/tasks/{$task->id}");

    $response->assertStatus(204);
    $this->assertSoftDeleted('tasks', ['id' => $task->id]);
});

it('excludes trashed tasks from the default list', function (): void {
    Task::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $trashedTask = Task::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $trashedTask->delete();

    $response = $this->actingAs($this->user)
        ->getJson("/api/app-user/tasks?organization_id={$this->organization->id}");

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
});

it('can list trashed tasks', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $task->delete();

    $response = $this->actingAs($this->user)
        ->getJson('/api/app-user/tasks/trash');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $task->id);
    $response->assertJsonPath('data.0.deleted_at', fn ($value) => $value !== null);
});

it('can restore a trashed task', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $task->delete();

    $response = $this->actingAs($this->user)
        ->postJson("/api/app-user/tasks/{$task->id}/restore");

    $response->assertStatus(200);
    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'deleted_at' => null,
    ]);
    $response->assertJsonPath('id', $task->id);
    $response->assertJsonPath('deleted_at', null);
});

it('can permanently delete a trashed task', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $task->delete();

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/app-user/tasks/{$task->id}/force");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});

it('cannot permanently delete a task that is not in trash', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/app-user/tasks/{$task->id}/force");

    $response->assertStatus(404);
    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
});

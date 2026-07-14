<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Application\Shared\Helpers\DateFormatHelper;
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
        'type' => 'general',
        'due_date' => '2026-07-15 00:00:00',
        'reminder_days' => 3,
        'organization_id' => $this->organization->id,
    ]);
    $response->assertJsonPath('title', 'Test Task');
    $response->assertJsonPath('type', 'general');
    $response->assertJsonPath('type_label', __('enums.task_type.general'));
    $response->assertJsonPath('due_date', DateFormatHelper::formatDateTimeWithDayOfWeek('2026-07-15 00:00:00'));
    $response->assertJsonPath('reminder_days', 3);
    $response->assertJsonPath('status', 'pending');
    $response->assertJsonPath('status_label', __('enums.task_status.pending'));
    $response->assertJsonPath('urgency_level', 'normal');
    $response->assertJsonPath('days_overdue', 0);
    $response->assertJsonPath('process_number', $this->process->process_number);
});

it('can create a suspension task with due date time', function (): void {
    $data = [
        'title' => 'Suspensión por acuerdo de pago',
        'description' => 'Proceso suspendido por 24 meses.',
        'type' => 'suspension',
        'due_date' => '2028-07-15 14:30:00',
        'reminder_days' => 0,
        'is_admin' => false,
        'process_id' => $this->process->id,
        'organization_id' => $this->organization->id,
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/app-user/tasks', $data);

    $response->assertStatus(201);
    $this->assertDatabaseHas('tasks', [
        'title' => 'Suspensión por acuerdo de pago',
        'type' => 'suspension',
        'due_date' => '2028-07-15 14:30:00',
        'organization_id' => $this->organization->id,
    ]);
    $this->assertDatabaseHas('organization_processes', [
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'status' => 'suspended',
        'is_active' => true,
    ]);
    $response->assertJsonPath('type', 'suspension');
    $response->assertJsonPath('type_label', __('enums.task_type.suspension'));
    $response->assertJsonPath('due_date', DateFormatHelper::formatDateTimeWithDayOfWeek('2028-07-15 14:30:00'));
});

it('fails to create a suspension task without process_id', function (): void {
    $data = [
        'title' => 'Suspensión sin proceso',
        'description' => 'Debe fallar.',
        'type' => 'suspension',
        'due_date' => '2028-07-15 14:30:00',
        'reminder_days' => 0,
        'is_admin' => false,
        'organization_id' => $this->organization->id,
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/app-user/tasks', $data);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['process_id']);
});

it('fails to create a suspension task when process_id is null', function (): void {
    $data = [
        'title' => 'Suspensión sin proceso',
        'description' => 'Debe fallar.',
        'type' => 'suspension',
        'due_date' => '2028-07-15 14:30:00',
        'reminder_days' => 0,
        'is_admin' => false,
        'process_id' => null,
        'organization_id' => $this->organization->id,
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/app-user/tasks', $data);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['process_id']);
});

it('fails to create a suspension task when the process already has one', function (): void {
    Task::factory()->suspension()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'status' => 'pending',
        'is_admin' => false,
    ]);

    $data = [
        'title' => 'Otra suspensión',
        'description' => 'No debe permitirse.',
        'type' => 'suspension',
        'due_date' => '2028-07-15 14:30:00',
        'reminder_days' => 0,
        'is_admin' => false,
        'process_id' => $this->process->id,
        'organization_id' => $this->organization->id,
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/app-user/tasks', $data);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['process_id']);
    expect($response->json('errors.process_id.0'))->toBe(__('process.already_has_suspension_task'));
});

it('allows creating a suspension when the previous one is completed', function (): void {
    Task::factory()->suspension()->completed()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'is_admin' => false,
    ]);

    $data = [
        'title' => 'Nueva suspensión',
        'description' => 'Permitida tras completar la anterior.',
        'type' => 'suspension',
        'due_date' => '2028-07-15 14:30:00',
        'reminder_days' => 0,
        'is_admin' => false,
        'process_id' => $this->process->id,
        'organization_id' => $this->organization->id,
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/app-user/tasks', $data);

    $response->assertCreated();
    $response->assertJsonPath('type', 'suspension');
});

it('fails to update a task to suspension when the process already has one', function (): void {
    Task::factory()->suspension()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'status' => 'pending',
        'is_admin' => false,
    ]);

    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'type' => 'general',
        'is_admin' => false,
    ]);

    $data = [
        'title' => 'Convertir a suspensión',
        'description' => 'No debe permitirse.',
        'type' => 'suspension',
        'due_date' => '2028-07-15 14:30:00',
        'reminder_days' => 0,
        'is_admin' => false,
        'process_id' => $this->process->id,
        'organization_id' => $this->organization->id,
    ];

    $response = $this->actingAs($this->user)
        ->putJson("/api/app-user/tasks/{$task->id}", $data);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['process_id']);
    expect($response->json('errors.process_id.0'))->toBe(__('process.already_has_suspension_task'));
});

it('allows updating the same suspension task for its process', function (): void {
    $task = Task::factory()->suspension()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'status' => 'pending',
        'is_admin' => false,
    ]);

    $data = [
        'title' => 'Suspensión actualizada',
        'description' => 'Puede editarse.',
        'type' => 'suspension',
        'due_date' => '2029-01-15 10:00:00',
        'reminder_days' => 5,
        'is_admin' => false,
        'process_id' => $this->process->id,
        'organization_id' => $this->organization->id,
    ];

    $response = $this->actingAs($this->user)
        ->putJson("/api/app-user/tasks/{$task->id}", $data);

    $response->assertOk();
    $response->assertJsonPath('title', 'Suspensión actualizada');
    $response->assertJsonPath('type', 'suspension');
});

it('fails to create a task with invalid type', function (): void {
    $data = [
        'title' => 'Test Task',
        'description' => 'Test Description',
        'type' => 'invalid',
        'due_date' => '2026-07-15',
        'reminder_days' => 3,
        'is_admin' => false,
        'process_id' => $this->process->id,
        'organization_id' => $this->organization->id,
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/app-user/tasks', $data);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['type']);
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

it('reactivates the process when a suspension task is completed', function (): void {
    $this->organization->processes()->updateExistingPivot($this->process->id, [
        'status' => 'suspended',
        'is_active' => true,
    ]);

    $task = Task::factory()->suspension()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'is_admin' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/api/app-user/tasks/{$task->id}/complete");

    $response->assertOk();
    $response->assertJsonPath('status', 'completed');
    $this->assertDatabaseHas('organization_processes', [
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'status' => 'active',
        'is_active' => true,
    ]);
});

it('reactivates the process when a suspension task is moved to trash', function (): void {
    $this->organization->processes()->updateExistingPivot($this->process->id, [
        'status' => 'suspended',
        'is_active' => true,
    ]);

    $task = Task::factory()->suspension()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'is_admin' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/app-user/tasks/{$task->id}");

    $response->assertStatus(204);
    $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    $this->assertDatabaseHas('organization_processes', [
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'status' => 'active',
        'is_active' => true,
    ]);
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
        'type' => 'suspension',
        'due_date' => '2026-08-01 09:15:00',
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
        'type' => 'suspension',
        'due_date' => '2026-08-01 09:15:00',
        'reminder_days' => 5,
        'is_admin' => false,
    ]);
    $this->assertDatabaseHas('organization_processes', [
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'status' => 'suspended',
    ]);
    $response->assertJsonPath('title', 'Updated Title');
    $response->assertJsonPath('type', 'suspension');
    $response->assertJsonPath('due_date', DateFormatHelper::formatDateTimeWithDayOfWeek('2026-08-01 09:15:00'));
    $response->assertJsonPath('reminder_days', 5);
    $response->assertJsonPath('is_admin', false);
});

it('resets urgency markers when due date is extended', function (): void {
    $task = Task::factory()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
        'due_date' => '2026-08-01 09:15:00',
        'last_notified_urgency_level' => 'alert_1',
        'last_due_reminder_sent_on' => '2026-07-01',
    ]);

    $data = [
        'title' => $task->title,
        'description' => $task->description,
        'type' => 'general',
        'due_date' => '2026-12-01 09:15:00',
        'reminder_days' => 3,
        'is_admin' => false,
        'process_id' => $this->process->id,
        'organization_id' => $this->organization->id,
    ];

    $response = $this->actingAs($this->user)
        ->putJson("/api/app-user/tasks/{$task->id}", $data);

    $response->assertStatus(200);
    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'due_date' => '2026-12-01 09:15:00',
        'last_notified_urgency_level' => null,
        'last_due_reminder_sent_on' => null,
    ]);
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

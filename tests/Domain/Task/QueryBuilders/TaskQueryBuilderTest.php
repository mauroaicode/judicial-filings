<?php

declare(strict_types=1);

namespace Tests\Domain\Task\QueryBuilders;

use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Models\Task;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->process = Process::factory()->create();
});

it('filters tasks by organization', function (): void {
    Task::factory()->create(['organization_id' => $this->organization->id]);
    Task::factory()->create(); // Another organization

    $results = Task::query()->whereOrganization($this->organization->id)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->organization_id)->toBe($this->organization->id);
});

it('filters tasks by admin flag', function (): void {
    Task::factory()->admin()->create(['organization_id' => $this->organization->id]);
    Task::factory()->create(['organization_id' => $this->organization->id, 'is_admin' => false]);

    $adminTasks = Task::query()
        ->whereOrganization($this->organization->id)
        ->whereAdmin(true)
        ->get();
    $userTasks = Task::query()
        ->whereOrganization($this->organization->id)
        ->whereAppUser()
        ->get();

    expect($adminTasks)->toHaveCount(1);
    expect($adminTasks->first()->is_admin)->toBeTrue();
    expect($userTasks)->toHaveCount(1);
    expect($userTasks->first()->is_admin)->toBeFalse();
});

it('filters tasks by process', function (): void {
    Task::factory()->create([
        'organization_id' => $this->organization->id,
        'process_id' => $this->process->id,
    ]);
    Task::factory()->create(['organization_id' => $this->organization->id, 'process_id' => null]);

    $results = Task::query()->whereProcess($this->process->id)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->process_id)->toBe($this->process->id);
});

it('filters tasks by type', function (): void {
    Task::factory()->create([
        'organization_id' => $this->organization->id,
        'type' => \Src\Domain\Task\Enums\TaskType::GENERAL,
    ]);
    Task::factory()->suspension()->create([
        'organization_id' => $this->organization->id,
    ]);

    $general = Task::query()
        ->whereOrganization($this->organization->id)
        ->whereType(\Src\Domain\Task\Enums\TaskType::GENERAL)
        ->get();
    $suspension = Task::query()
        ->whereOrganization($this->organization->id)
        ->whereType(\Src\Domain\Task\Enums\TaskType::SUSPENSION)
        ->get();

    expect($general)->toHaveCount(1);
    expect($suspension)->toHaveCount(1);
});

it('filters tasks by status', function (): void {
    Task::factory()->create([
        'organization_id' => $this->organization->id,
        'status' => TaskStatus::PENDING,
    ]);
    Task::factory()->completed()->create([
        'organization_id' => $this->organization->id,
    ]);

    $pendingResults = Task::query()
        ->whereOrganization($this->organization->id)
        ->whereStatus(TaskStatus::PENDING)
        ->get();
    $completedResults = Task::query()
        ->whereOrganization($this->organization->id)
        ->whereStatus(TaskStatus::COMPLETED)
        ->get();

    expect($pendingResults)->toHaveCount(1);
    expect($completedResults)->toHaveCount(1);
});

it('excludes completed tasks', function (): void {
    Task::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    Task::factory()->completed()->create([
        'organization_id' => $this->organization->id,
    ]);

    $results = Task::query()
        ->whereOrganization($this->organization->id)
        ->excludingCompleted()
        ->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->status)->toBe(TaskStatus::PENDING);
});

it('orders tasks by created at', function (): void {
    $task1 = Task::factory()->create([
        'organization_id' => $this->organization->id,
        'created_at' => now()->subDay(),
    ]);
    $task2 = Task::factory()->create([
        'organization_id' => $this->organization->id,
        'created_at' => now(),
    ]);

    $results = Task::query()
        ->whereOrganization($this->organization->id)
        ->orderedByCreatedAt()
        ->get();

    expect($results->first()->id)->toBe($task2->id);
    expect($results->last()->id)->toBe($task1->id);
});

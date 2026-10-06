<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Src\Application\Admin\Process\Jobs\GenerateOrganizationProcessExportJob;
use Src\Application\Admin\Process\Services\OrganizationProcessExportActionQueryService;
use Src\Application\Admin\Process\Services\OrganizationProcessExportQueryService;
use Src\Application\Shared\Notifications\ProcessExportFinishedNotification;
use Src\Application\Shared\Services\Notification\NotifyAdminProcessExportFinishedService;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessExportStatus;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessExport;
use Src\Domain\Process\Models\ProcessSubject;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    Queue::fake();
    Storage::fake('local');
    Notification::fake();

    $this->user = User::factory()->create([
        'email' => 'admin-export@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create();
});

it('requires authentication to queue an export', function (): void {
    $this->postJson("/api/admin/organizations/{$this->organization->id}/processes/export")
        ->assertStatus(401);
});

it('queues an organization process export', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson("/api/admin/organizations/{$this->organization->id}/processes/export", [
            'status' => 'active',
            'include_plaintiffs' => true,
            'include_defendants' => false,
            'include_other_subjects' => false,
            'created_at_from' => '2026-01-01',
            'created_at_to' => '2026-12-31',
        ]);

    $response->assertStatus(202)
        ->assertJsonPath('data.status', ProcessExportStatus::Pending->value)
        ->assertJsonPath('data.organization_id', $this->organization->id)
        ->assertJsonPath('data.filters.status', 'active')
        ->assertJsonPath('data.filters.include_defendants', false);

    $exportId = $response->json('data.id');

    Queue::assertPushed(GenerateOrganizationProcessExportJob::class, function (GenerateOrganizationProcessExportJob $job) use ($exportId): bool {
        return $job->exportId === $exportId;
    });

    $this->assertDatabaseHas('process_exports', [
        'id' => $exportId,
        'organization_id' => $this->organization->id,
        'requested_by' => $this->user->id,
        'status' => ProcessExportStatus::Pending->value,
    ]);
});

it('validates status filter', function (): void {
    $this->actingAs($this->user)
        ->postJson("/api/admin/organizations/{$this->organization->id}/processes/export", [
            'status' => 'archived',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('shows export status for the organization', function (): void {
    $export = ProcessExport::factory()->completed()->create([
        'organization_id' => $this->organization->id,
        'requested_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/admin/organizations/{$this->organization->id}/processes/exports/{$export->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $export->id)
        ->assertJsonPath('data.downloadable', true)
        ->assertJsonPath('data.status', ProcessExportStatus::Completed->value);
});

it('does not show exports from another organization', function (): void {
    $otherOrganization = Organization::factory()->create();
    $export = ProcessExport::factory()->completed()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/admin/organizations/{$this->organization->id}/processes/exports/{$export->id}")
        ->assertNotFound();
});

it('generates an excel file with plaintiffs formatted like the datagrip query', function (): void {
    $process = Process::factory()->create([
        'process_number' => '76001333301720230003301',
        'court' => 'Juzgado 017 Administrativo',
        'process_class' => 'Nulidad',
    ]);

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'MARIA GARCIA',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA XYZ',
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => 'active',
        'lawyer_role' => ProcessLawyerRole::PLAINTIFF->value,
    ]);

    config(['process-export.defer_while_sync_active' => false]);

    $export = ProcessExport::factory()->create([
        'organization_id' => $this->organization->id,
        'requested_by' => $this->user->id,
        'filters' => [
            'status' => 'active',
            'include_plaintiffs' => true,
            'include_defendants' => true,
            'include_other_subjects' => true,
        ],
    ]);

    (new GenerateOrganizationProcessExportJob($export->id))->handle(
        app(OrganizationProcessExportQueryService::class),
        app(OrganizationProcessExportActionQueryService::class),
        app(NotifyAdminProcessExportFinishedService::class),
    );

    $export->refresh();

    expect($export->status)->toBe(ProcessExportStatus::Completed)
        ->and($export->row_count)->toBe(1)
        ->and($export->action_row_count)->toBe(0)
        ->and($export->file_path)->not->toBeNull()
        ->and($export->file_name)->toStartWith('procesos-')
        ->and($export->file_name)->toContain(Str::slug($this->organization->name))
        ->and($export->file_name)->toEndWith('.xlsx');

    Storage::disk('local')->assertExists($export->file_path);

    Notification::assertSentTo($this->user, ProcessExportFinishedNotification::class);

    $this->actingAs($this->user)
        ->get("/api/admin/organizations/{$this->organization->id}/processes/exports/{$export->id}/download")
        ->assertOk();
});

it('generates a workbook with actuaciones sheet when include_actions is true', function (): void {
    $process = Process::factory()->create([
        'process_number' => '76001333301720230003399',
        'court' => 'Juzgado 01',
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => 'active',
    ]);

    ProcessAction::factory()->count(2)->create([
        'process_id' => $process->id,
        'action_date' => '2026-03-15',
        'registration_date' => '2026-03-15',
    ]);

    config(['process-export.defer_while_sync_active' => false]);

    $export = ProcessExport::factory()->create([
        'organization_id' => $this->organization->id,
        'requested_by' => $this->user->id,
        'filters' => [
            'status' => 'active',
            'include_plaintiffs' => false,
            'include_defendants' => false,
            'include_other_subjects' => false,
            'include_actions' => true,
            'actions_from' => '2026-01-01',
            'actions_to' => '2026-12-31',
        ],
    ]);

    (new GenerateOrganizationProcessExportJob($export->id))->handle(
        app(OrganizationProcessExportQueryService::class),
        app(OrganizationProcessExportActionQueryService::class),
        app(NotifyAdminProcessExportFinishedService::class),
    );

    $export->refresh();

    expect($export->status)->toBe(ProcessExportStatus::Completed)
        ->and($export->row_count)->toBe(1)
        ->and($export->action_row_count)->toBe(2)
        ->and($export->file_name)->toStartWith('procesos-y-actuaciones-');

    Storage::disk('local')->assertExists((string) $export->file_path);
});

it('lists organization export history', function (): void {
    ProcessExport::factory()->completed()->create([
        'organization_id' => $this->organization->id,
        'requested_by' => $this->user->id,
        'filters' => ['status' => 'active', 'include_plaintiffs' => true],
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/admin/organizations/{$this->organization->id}/processes/exports")
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.filters.status', 'active')
        ->assertJsonPath('data.0.can_rerun', true);
});

it('requeues an export using stored filters from history', function (): void {
    $source = ProcessExport::factory()->completed()->create([
        'organization_id' => $this->organization->id,
        'requested_by' => $this->user->id,
        'filters' => [
            'status' => 'inactive',
            'include_plaintiffs' => false,
            'include_defendants' => true,
            'include_other_subjects' => false,
            'created_at_from' => '2026-02-01',
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/admin/organizations/{$this->organization->id}/processes/exports/{$source->id}/rerun");

    $response->assertStatus(202)
        ->assertJsonPath('data.status', ProcessExportStatus::Pending->value)
        ->assertJsonPath('data.filters.status', 'inactive')
        ->assertJsonPath('data.filters.include_defendants', true)
        ->assertJsonPath('data.filters.include_plaintiffs', false);

    expect($response->json('data.id'))->not->toBe($source->id);

    Queue::assertPushed(GenerateOrganizationProcessExportJob::class, function (GenerateOrganizationProcessExportJob $job) use ($response): bool {
        return $job->exportId === $response->json('data.id');
    });
});

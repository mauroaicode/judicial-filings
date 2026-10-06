<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessExportStatus;
use Src\Domain\Process\Models\ProcessExport;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    ProcessExport::query()->delete();

    $this->user = User::factory()->create([
        'email' => 'admin-export-history@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create(['name' => 'Org Export Alpha']);
    $this->otherOrganization = Organization::factory()->create(['name' => 'Org Export Beta']);
});

it('requires authentication to access admin export history', function (): void {
    $this->getJson('/api/admin/processes/export-history')
        ->assertStatus(401);
});

it('returns paginated export history for all organizations with excel details', function (): void {
    $completedAt = now()->subMinutes(5);

    $export = ProcessExport::factory()->completed()->create([
        'organization_id' => $this->organization->id,
        'requested_by' => $this->user->id,
        'filters' => [
            'status' => 'active',
            'include_plaintiffs' => true,
            'include_defendants' => false,
            'include_other_subjects' => false,
        ],
        'row_count' => 3,
        'file_name' => 'procesos-alpha.xlsx',
        'completed_at' => $completedAt,
        'created_at' => $completedAt->copy()->subMinute(),
    ]);

    ProcessExport::factory()->create([
        'organization_id' => $this->otherOrganization->id,
        'status' => ProcessExportStatus::Failed,
        'filters' => ['status' => 'inactive'],
        'error_message' => 'boom',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes/export-history');

    $response->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'organization_id',
                    'organization_name',
                    'requested_by_name',
                    'status',
                    'status_label',
                    'filters',
                    'row_count',
                    'action_row_count',
                    'file_name',
                    'can_rerun',
                    'downloadable',
                    'download_url',
                    'created_at',
                    'completed_at',
                ],
            ],
        ]);

    $first = collect($response->json('data'))->firstWhere('id', $export->id);

    expect($first['organization_name'])->toBe('Org Export Alpha')
        ->and($first['file_name'])->toBe('procesos-alpha.xlsx')
        ->and($first['row_count'])->toBe(3)
        ->and($first['status_label'])->toBe(ProcessExportStatus::Completed->getLabel())
        ->and($first['can_rerun'])->toBeTrue()
        ->and($first['created_at'])->toBe(DateFormatHelper::formatDateWithTime($export->created_at))
        ->and($first['completed_at'])->toBe(DateFormatHelper::formatDateWithTime($completedAt));
});

it('filters export history by organization name and status', function (): void {
    ProcessExport::factory()->completed()->create([
        'organization_id' => $this->organization->id,
    ]);
    ProcessExport::factory()->create([
        'organization_id' => $this->otherOrganization->id,
        'status' => ProcessExportStatus::Failed,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/admin/processes/export-history?organization=Alpha&status=completed')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.organization_name', 'Org Export Alpha')
        ->assertJsonPath('data.0.status', ProcessExportStatus::Completed->value);
});

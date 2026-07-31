<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessImportBatch;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    // Admin history is global; wipe batches so other test files do not inflate counts.
    ProcessImportBatch::query()->delete();

    $this->user = User::factory()->create([
        'email' => 'admin-import-history@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create(['name' => 'Org Alpha']);
    $this->otherOrganization = Organization::factory()->create(['name' => 'Org Beta']);
});

it('requires authentication to access admin import history', function (): void {
    $this->getJson('/api/admin/processes/import-history')
        ->assertStatus(401);
});

it('returns empty paginated list when there are no import batches', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/admin/processes/import-history')
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'current_page',
            'total',
        ])
        ->assertJsonCount(0, 'data');
});

it('returns import batches from all organizations', function (): void {
    ProcessImportBatch::factory()->count(2)->create([
        'organization_id' => $this->organization->id,
    ]);

    ProcessImportBatch::factory()->count(3)->create([
        'organization_id' => $this->otherOrganization->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes/import-history');

    $response->assertOk();
    expect($response->json('total'))->toBe(5);
    $response->assertJsonCount(5, 'data');
});

it('returns batches ordered by created_at descending', function (): void {
    $oldest = ProcessImportBatch::factory()->create([
        'organization_id' => $this->organization->id,
        'created_at' => now()->subDays(5),
    ]);

    $newest = ProcessImportBatch::factory()->create([
        'organization_id' => $this->organization->id,
        'created_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes/import-history');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->toArray();

    expect($ids[0])->toBe($newest->id)
        ->and($ids[1])->toBe($oldest->id);
});

it('returns batches with null organization_id for cross-organization imports', function (): void {
    ProcessImportBatch::factory()->completed()->create([
        'organization_id' => null,
        'file_name' => 'actuaciones_multi_org.xlsx',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes/import-history');

    $response->assertOk();
    expect($response->json('total'))->toBe(1)
        ->and($response->json('data.0.organization_id'))->toBeNull()
        ->and($response->json('data.0.organization_name'))->toBe('')
        ->and($response->json('data.0.file_name'))->toBe('actuaciones_multi_org.xlsx');
});

it('returns expected resource structure including errors and organization', function (): void {
    $errors = [
        ['process_number' => '76001400300520150055500', 'reason' => 'Radicado no disponible.'],
        ['process_number' => '76001400300420190109600', 'reason' => 'Error SQL de ejemplo.'],
    ];

    ProcessImportBatch::factory()->completed()->create([
        'organization_id' => $this->organization->id,
        'file_name' => 'ADMIN.xlsx',
        'total_count' => 10,
        'success_count' => 8,
        'failed_count' => 2,
        'multiple_instances_count' => 1,
        'enqueued_process_numbers' => ['11001020300020260151400'],
        'errors' => $errors,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes/import-history');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'organization_id',
                'organization_name',
                'file_name',
                'is_private_import',
                'total_count',
                'success_count',
                'failed_count',
                'multiple_instances_count',
                'status',
                'status_label',
                'enqueued_process_numbers',
                'errors',
                'completed_at',
                'created_at',
            ],
        ],
    ]);

    $first = $response->json('data.0');

    expect($first['organization_id'])->toBe($this->organization->id)
        ->and($first['organization_name'])->toBe('Org Alpha')
        ->and($first['file_name'])->toBe('ADMIN.xlsx')
        ->and($first['is_private_import'])->toBeFalse()
        ->and($first['errors'])->toBe($errors)
        ->and($first['status'])->toBe('completed')
        ->and($first['status_label'])->toBe(__('enums.process_import_batch_status.completed'));
});

it('respects status filter', function (): void {
    ProcessImportBatch::factory()->completed()->create([
        'organization_id' => $this->organization->id,
    ]);

    ProcessImportBatch::factory()->processing()->create([
        'organization_id' => $this->otherOrganization->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes/import-history?status=processing');

    $response->assertOk();
    expect($response->json('total'))->toBe(1)
        ->and($response->json('data.0.status'))->toBe('processing');
});

it('respects per_page query parameter', function (): void {
    ProcessImportBatch::factory()->count(10)->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes/import-history?per_page=4');

    $response->assertOk();
    $response->assertJsonCount(4, 'data');
    expect($response->json('total'))->toBe(10);
});

it('filters by organization name partial match', function (): void {
    ProcessImportBatch::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    ProcessImportBatch::factory()->create([
        'organization_id' => $this->otherOrganization->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes/import-history?organization=Beta');

    $response->assertOk();
    expect($response->json('total'))->toBe(1)
        ->and($response->json('data.0.organization_name'))->toBe('Org Beta');
});

it('filters by file name partial match', function (): void {
    ProcessImportBatch::factory()->create([
        'organization_id' => $this->organization->id,
        'file_name' => 'reporte_abril.xlsx',
    ]);

    ProcessImportBatch::factory()->create([
        'organization_id' => $this->organization->id,
        'file_name' => 'otro.xlsx',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes/import-history?file_name=abril');

    $response->assertOk();
    expect($response->json('total'))->toBe(1)
        ->and($response->json('data.0.file_name'))->toBe('reporte_abril.xlsx');
});

it('filters has_errors true to batches with non-empty errors json', function (): void {
    ProcessImportBatch::factory()->completed()->create([
        'organization_id' => $this->organization->id,
        'errors' => [
            ['process_number' => '76001400300520150055500', 'reason' => 'No disponible'],
        ],
    ]);

    ProcessImportBatch::factory()->completed()->create([
        'organization_id' => $this->organization->id,
        'errors' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes/import-history?has_errors=1');

    $response->assertOk();
    expect($response->json('total'))->toBe(1);
});

it('filters has_errors false to batches without errors in json', function (): void {
    ProcessImportBatch::factory()->completed()->create([
        'organization_id' => $this->organization->id,
        'errors' => [
            ['process_number' => '76001400300520150055500', 'reason' => 'No disponible'],
        ],
    ]);

    ProcessImportBatch::factory()->completed()->create([
        'organization_id' => $this->organization->id,
        'errors' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes/import-history?has_errors=0');

    $response->assertOk();
    expect($response->json('total'))->toBe(1);
});

it('filters by created_at date range', function (): void {
    $tooOld = ProcessImportBatch::factory()->create([
        'organization_id' => $this->organization->id,
        'created_at' => now()->subDays(20),
    ]);

    $inRange = ProcessImportBatch::factory()->create([
        'organization_id' => $this->organization->id,
        'created_at' => now()->subDays(3),
    ]);

    $from = now()->subDays(10)->format('Y-m-d');
    $to = now()->format('Y-m-d');

    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/processes/import-history?created_at_from={$from}&created_at_to={$to}");

    $response->assertOk();
    expect($response->json('total'))->toBe(1)
        ->and($response->json('data.0.id'))->toBe($inRange->id);

    expect(collect($response->json('data'))->pluck('id')->contains($tooOld->id))->toBeFalse();
});

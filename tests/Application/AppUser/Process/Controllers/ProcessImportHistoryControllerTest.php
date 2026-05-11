<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\Process\Controllers;

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessImportBatch;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create([
        'email' => 'importhistory@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);

    $this->appUser->organizations()->attach($this->organization->id, [
        'is_owner' => true,
    ]);
});

it('requires authentication to access import history', function (): void {
    $response = $this->getJson('/api/app-user/processes/import-history');

    $response->assertStatus(401);
});

it('returns 422 when user has no organization', function (): void {
    $userWithoutOrg = AppUser::factory()->create([
        'email' => 'noorg-import@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($userWithoutOrg)
        ->getJson('/api/app-user/processes/import-history');

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('process.user_has_no_organization')],
    ]);
});

it('returns an empty paginated list when the organization has no imports', function (): void {
    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes/import-history');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data',
        'current_page',
        'total',
    ]);
    $response->assertJsonCount(0, 'data');
});

it('returns a paginated list of import batches for the organization', function (): void {
    ProcessImportBatch::factory()->count(3)->create([
        'organization_id' => $this->organization->id,
    ]);

    // Batch belonging to a different organization — must NOT appear
    ProcessImportBatch::factory()->create();

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes/import-history');

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'data');
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

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes/import-history');

    $response->assertStatus(200);

    $ids = collect($response->json('data'))->pluck('id')->toArray();

    expect($ids[0])->toBe($newest->id)
        ->and($ids[1])->toBe($oldest->id);
});

it('returns the expected resource structure', function (): void {
    ProcessImportBatch::factory()->completed()->create([
        'organization_id' => $this->organization->id,
        'file_name' => 'PRUEBA.xlsx',
        'total_count' => 10,
        'success_count' => 8,
        'failed_count' => 2,
        'multiple_instances_count' => 1,
        'enqueued_process_numbers' => ['11001020300020260151400', '11001418903520230038100'],
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes/import-history');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'file_name',
                'is_private_import',
                'total_count',
                'success_count',
                'failed_count',
                'multiple_instances_count',
                'status',
                'status_label',
                'enqueued_process_numbers',
                'completed_at',
                'created_at',
            ],
        ],
    ]);

    $first = $response->json('data.0');

    expect($first['file_name'])->toBe('PRUEBA.xlsx')
        ->and($first['is_private_import'])->toBeFalse()
        ->and($first['total_count'])->toBe(10)
        ->and($first['success_count'])->toBe(8)
        ->and($first['failed_count'])->toBe(2)
        ->and($first['multiple_instances_count'])->toBe(1)
        ->and($first['status'])->toBe('completed')
        ->and($first['status_label'])->toBe(__('enums.process_import_batch_status.completed'))
        ->and($first['enqueued_process_numbers'])->toBe(['11001020300020260151400', '11001418903520230038100']);
});

it('returns translated status label for processing status', function (): void {
    ProcessImportBatch::factory()->processing()->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes/import-history');

    $response->assertStatus(200);

    $first = $response->json('data.0');

    expect($first['status'])->toBe('processing')
        ->and($first['status_label'])->toBe(__('enums.process_import_batch_status.processing'));
});

it('returns translated status label for failed status', function (): void {
    ProcessImportBatch::factory()->failed()->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes/import-history');

    $response->assertStatus(200);

    $first = $response->json('data.0');

    expect($first['status'])->toBe('failed')
        ->and($first['status_label'])->toBe(__('enums.process_import_batch_status.failed'));
});

it('returns null completed_at when batch is still processing', function (): void {
    ProcessImportBatch::factory()->processing()->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes/import-history');

    $response->assertStatus(200);

    expect($response->json('data.0.completed_at'))->toBeNull();
});

it('respects per_page query parameter', function (): void {
    ProcessImportBatch::factory()->count(10)->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes/import-history?per_page=3');

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'data');
    expect($response->json('total'))->toBe(10);
});

it('only returns batches belonging to the authenticated user organization', function (): void {
    $otherOrganization = Organization::factory()->create();

    ProcessImportBatch::factory()->count(2)->create([
        'organization_id' => $this->organization->id,
    ]);

    ProcessImportBatch::factory()->count(5)->create([
        'organization_id' => $otherOrganization->id,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes/import-history');

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'data');
});

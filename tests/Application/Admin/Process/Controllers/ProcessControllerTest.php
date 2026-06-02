<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessDataSource;
use Src\Domain\Process\Models\ProcessSubject;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    // Clean up all processes before each test in this file
    // DatabaseTransactions should clean up processes from other test files,
    // but we need to clean up here to ensure tests in this file don't interfere with each other
    // Since tests run sequentially, this won't affect other test files
    Process::query()->delete();

    $this->user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    // Assign admin role to user
    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);
});

it('requires authentication to list processes', function (): void {
    $response = $this->getJson('/api/admin/processes');

    $response->assertStatus(401);
});

it('returns empty list when there are no processes', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes');

    if ($response->status() === 500) {
        dump('500 response body:', $response->getContent());
        dump('500 json:', $response->json());
    }
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data',
        'current_page',
        'per_page',
        'total',
    ]);
    expect($response->json('data'))->toBeEmpty();
});

it('returns all processes from all organizations', function (): void {
    $organization1 = Organization::factory()->create(['name' => 'Organization 1']);
    $organization2 = Organization::factory()->create(['name' => 'Organization 2']);

    $process1 = Process::factory()->create();
    $process2 = Process::factory()->create();
    $process3 = Process::factory()->create();

    // Attach processes to different organizations
    $process1->organizations()->attach($organization1->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($organization1->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process3->organizations()->attach($organization2->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(3);
    expect($response->json('total'))->toBe(3);
});

it('shows organization name in response', function (): void {
    $organization = Organization::factory()->create(['name' => 'Test Organization']);
    $process = Process::factory()->create();

    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes');

    $response->assertStatus(200);
    expect($response->json('data.0.organization'))->toBe('Test Organization');
    expect($response->json('data.0.organizations_count'))->toBe(1);
});

it('includes manual sync flag and data source in admin list response', function (): void {
    $organization = Organization::factory()->create(['name' => 'Test Organization']);
    $process = Process::factory()->create([
        'is_manual_sync' => true,
        'process_data_source_id' => ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::Samai),
    ]);

    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes');

    $response->assertStatus(200);
    expect($response->json('data.0.is_manual_sync'))->toBeTrue()
        ->and($response->json('data.0.data_source_slug'))->toBe('samai')
        ->and($response->json('data.0.data_source_name'))->toBe('Consejo de Estado (SAMAI)');
});

it('shows organization count when process has multiple organizations', function (): void {
    $organization1 = Organization::factory()->create(['name' => 'Organization 1']);
    $organization2 = Organization::factory()->create(['name' => 'Organization 2']);
    $organization3 = Organization::factory()->create(['name' => 'Organization 3']);

    $process = Process::factory()->create();

    $process->organizations()->attach($organization1->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process->organizations()->attach($organization2->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process->organizations()->attach($organization3->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes');

    $response->assertStatus(200);
    expect($response->json('data.0.organization'))->toContain('(+2)');
    expect($response->json('data.0.organizations_count'))->toBe(3);
});

it('shows plaintiff and defendant information', function (): void {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create();

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ GARCIA',
    ]);

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA ABC S.A.S.',
    ]);

    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes');

    $response->assertStatus(200);
    expect($response->json('data.0.plaintiff'))->toContain('Juan Perez Garcia');
    expect($response->json('data.0.defendant'))->toContain('Empresa Abc S.A.S.');
});

it('shows plaintiff count when there are multiple plaintiffs', function (): void {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create();

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ GARCIA',
    ]);

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'MARIA LOPEZ RODRIGUEZ',
    ]);

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'CARLOS GOMEZ MARTINEZ',
    ]);

    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes');

    $response->assertStatus(200);
    expect($response->json('data.0.plaintiff'))->toContain('(+2)');
    expect($response->json('data.0.plaintiffs_count'))->toBe(3);
});

it('shows defendant count when there are multiple defendants', function (): void {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create();

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA ABC S.A.S.',
    ]);

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA XYZ LTDA.',
    ]);

    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes');

    $response->assertStatus(200);
    expect($response->json('data.0.defendant'))->toContain('(+1)');
    expect($response->json('data.0.defendants_count'))->toBe(2);
});

it('orders processes by created_at descending', function (): void {
    $organization = Organization::factory()->create();

    $process1 = Process::factory()->create();
    $process2 = Process::factory()->create();
    $process3 = Process::factory()->create();

    // Attach with different created_at dates
    $process1->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ]);

    $process2->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'created_at' => now()->subDays(1),
        'updated_at' => now()->subDays(1),
    ]);

    $process3->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(3);
    // Most recent first
    expect($response->json('data.0.id'))->toBe($process3->id);
    expect($response->json('data.1.id'))->toBe($process2->id);
    expect($response->json('data.2.id'))->toBe($process1->id);
});

it('returns paginated results', function (): void {
    $organization = Organization::factory()->create();

    // Create 25 processes
    $processes = Process::factory()->count(25)->create();

    foreach ($processes as $process) {
        $process->organizations()->attach($organization->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
        ]);
    }

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes?per_page=10');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(10);
    expect($response->json('per_page'))->toBe(10);
    expect($response->json('total'))->toBe(25);
    expect($response->json('last_page'))->toBe(3);
});

it('includes index field that continues across pages', function (): void {
    $organization = Organization::factory()->create();

    // Create 25 processes
    $processes = Process::factory()->count(25)->create();

    foreach ($processes as $process) {
        $process->organizations()->attach($organization->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
        ]);
    }

    // First page (per_page=10)
    $responsePage1 = $this->actingAs($this->user)
        ->getJson('/api/admin/processes?per_page=10&page=1');

    $responsePage1->assertStatus(200);
    expect($responsePage1->json('data.0.index'))->toBe(1);
    expect($responsePage1->json('data.9.index'))->toBe(10);

    // Second page
    $responsePage2 = $this->actingAs($this->user)
        ->getJson('/api/admin/processes?per_page=10&page=2');

    $responsePage2->assertStatus(200);
    expect($responsePage2->json('data.0.index'))->toBe(11);
    expect($responsePage2->json('data.9.index'))->toBe(20);
});

it('returns correct resource structure', function (): void {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create([
        'process_number' => '76001333301320170009301',
        'court' => 'JUZGADO 017 ADMINISTRATIVO',
        'process_class' => 'ACCION DE REPARACION DIRECTA',
        'is_private' => false,
        'has_multiple_instances' => true,
    ]);

    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'index',
                'id',
                'process_number',
                'court',
                'speaker',
                'process_class',
                'subclass_process',
                'process_date',
                'last_activity_date',
                'is_private',
                'has_multiple_instances',
                'is_manual_sync',
                'data_source_slug',
                'data_source_name',
                'status_label',
                'created_at',
                'term_start_date',
                'term_end_date',
                'plaintiff',
                'defendant',
                'organization',
                'organizations_count',
                'plaintiffs_count',
                'defendants_count',
                'others_count',
                'subjects_count',
                'organizations',
                'plaintiffs',
                'defendants',
                'other_subject',
                'others',
                'instances' => [
                    '*' => [
                        'index',
                        'id',
                        'process_number',
                        'court',
                        'speaker',
                        'process_class',
                        'plaintiff',
                        'defendant',
                        'organization',
                        'organizations_count',
                        'plaintiffs_count',
                        'defendants_count',
                        'others_count',
                        'subjects_count',
                        'organizations',
                        'plaintiffs',
                        'defendants',
                        'other_subject',
                        'others',
                    ],
                ],
            ],
        ],
        'current_page',
        'per_page',
        'total',
    ]);
});

it('filters processes by process_number', function (): void {
    $organization = Organization::factory()->create();

    $process1 = Process::factory()->create(['process_number' => '76001333301320170009301']);
    $process2 = Process::factory()->create(['process_number' => '76001333301320170009302']);

    $process1->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes?process_number=09301');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.process_number'))->toBe('76001333301320170009301');
});

it('filters processes by court', function (): void {
    $organization = Organization::factory()->create();

    $process1 = Process::factory()->create(['court' => 'JUZGADO 017 ADMINISTRATIVO']);
    $process2 = Process::factory()->create(['court' => 'JUZGADO 006 CIVIL']);

    $process1->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes?court=017');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.court'))->toContain('017');
});

it('filters processes by judicial status on processes table not pivot', function (): void {
    $organization = Organization::factory()->create();

    $processActive = Process::factory()->create(['status' => 'activo']);
    $processInactive = Process::factory()->create(['status' => 'inactivo']);

    $processActive->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $processInactive->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $responseActive = $this->actingAs($this->user)
        ->getJson('/api/admin/processes?status=active');

    $responseActive->assertStatus(200);
    expect($responseActive->json('data'))->toHaveCount(1);
    expect($responseActive->json('data.0.id'))->toBe($processActive->id);

    $responseInactive = $this->actingAs($this->user)
        ->getJson('/api/admin/processes?status=inactive');

    $responseInactive->assertStatus(200);
    expect($responseInactive->json('data'))->toHaveCount(1);
    expect($responseInactive->json('data.0.id'))->toBe($processInactive->id);
});

it('filters processes by organization name', function (): void {
    $organization1 = Organization::factory()->create(['name' => 'Mauricio SAS']);
    $organization2 = Organization::factory()->create(['name' => 'Empresa ABC']);
    $organization3 = Organization::factory()->create(['name' => 'Otra Empresa']);

    $process1 = Process::factory()->create();
    $process2 = Process::factory()->create();
    $process3 = Process::factory()->create();

    // Process 1 has only "Mauricio SAS"
    $process1->organizations()->attach($organization1->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    // Process 2 has multiple organizations including "Mauricio SAS"
    $process2->organizations()->attach($organization1->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($organization2->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($organization3->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    // Process 3 has only "Empresa ABC"
    $process3->organizations()->attach($organization2->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes?organization=Mauricio');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0.id'))->toBeIn([$process1->id, $process2->id]);
    expect($response->json('data.1.id'))->toBeIn([$process1->id, $process2->id]);
});

it('filters processes by organization name with exact match', function (): void {
    $organization1 = Organization::factory()->create(['name' => 'Test Mauricio SAS']);
    $organization2 = Organization::factory()->create(['name' => 'Test Mauricio Gutierrez']);

    $process1 = Process::factory()->create();
    $process2 = Process::factory()->create();

    $process1->organizations()->attach($organization1->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $process2->organizations()->attach($organization2->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes?organization=SAS');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($process1->id);
});

it('filters processes by privacy query param private or public', function (): void {
    $organization = Organization::factory()->create();
    $private = Process::factory()->create([
        'process_number' => '11001418901234567890123',
        'is_private' => true,
    ]);
    $public = Process::factory()->create([
        'process_number' => '22001418901234567890123',
        'is_private' => false,
    ]);

    foreach ([$private, $public] as $process) {
        $process->organizations()->attach($organization->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
        ]);
    }

    $onlyPrivate = $this->actingAs($this->user)
        ->getJson('/api/admin/processes?privacy=private');
    $onlyPrivate->assertStatus(200);
    expect($onlyPrivate->json('total'))->toBe(1);
    expect($onlyPrivate->json('data'))->toHaveCount(1);
    expect($onlyPrivate->json('data.0.is_private'))->toBeTrue();

    $onlyPublic = $this->actingAs($this->user)
        ->getJson('/api/admin/processes?privacy=public');
    $onlyPublic->assertStatus(200);
    expect($onlyPublic->json('total'))->toBe(1);
    expect($onlyPublic->json('data'))->toHaveCount(1);
    expect($onlyPublic->json('data.0.is_private'))->toBeFalse();

    $all = $this->actingAs($this->user)
        ->getJson('/api/admin/processes');
    $all->assertStatus(200);
    expect($all->json('total'))->toBe(2);
});

it('rejects invalid privacy query value', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes?privacy=all');

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['privacy']);
});

it('uses earliest organization registration date for created_at', function (): void {
    $organization1 = Organization::factory()->create();
    $organization2 = Organization::factory()->create();

    $process = Process::factory()->create();

    $earlierDate = now()->subDays(5);
    $laterDate = now()->subDays(2);

    // Attach to first organization with earlier date
    $process->organizations()->attach($organization1->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    // Update pivot created_at directly using DB
    \Illuminate\Support\Facades\DB::table('organization_processes')
        ->where('process_id', $process->id)
        ->where('organization_id', $organization1->id)
        ->update([
            'created_at' => $earlierDate,
            'updated_at' => $earlierDate,
        ]);

    // Attach to second organization with later date
    $process->organizations()->attach($organization2->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    // Update pivot created_at directly using DB
    \Illuminate\Support\Facades\DB::table('organization_processes')
        ->where('process_id', $process->id)
        ->where('organization_id', $organization2->id)
        ->update([
            'created_at' => $laterDate,
            'updated_at' => $laterDate,
        ]);

    // Refresh the process to reload relationships
    $process->refresh();
    $process->load('organizations');

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes');

    $response->assertStatus(200);
    // Should use the earliest date (5 days ago), formatted for display
    expect($response->json('data.0.created_at'))->toBe(\Src\Application\Shared\Helpers\DateFormatHelper::formatDate($earlierDate));
});

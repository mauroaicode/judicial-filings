<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);

    // Attach user to organization
    $this->appUser->organizations()->attach($this->organization->id, [
        'is_owner' => true,
    ]);
});

it('requires authentication to list processes', function (): void {
    $response = $this->getJson('/api/app-user/processes');

    $response->assertStatus(401);
});

it('returns empty list when organization has no processes', function (): void {
    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data',
        'current_page',
        'per_page',
        'total',
    ]);
    expect($response->json('data'))->toBeEmpty();
});

it('returns processes for user organization', function (): void {
    $process1 = Process::factory()->create();
    $process2 = Process::factory()->create();
    $process3 = Process::factory()->create();

    // Attach processes to organization
    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process3->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(3);
    expect($response->json('total'))->toBe(3);
});

it('does not return processes from other organizations', function (): void {
    $otherOrganization = Organization::factory()->create();

    $process1 = Process::factory()->create();
    $process2 = Process::factory()->create();

    // Attach process1 to user's organization
    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    // Attach process2 to other organization
    $process2->organizations()->attach($otherOrganization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($process1->id);
});

it('filters processes by process_number', function (): void {
    $process1 = Process::factory()->create(['process_number' => '76001333301320170009301']);
    $process2 = Process::factory()->create(['process_number' => '76001333301320170009302']);

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?process_number=09301');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.process_number'))->toBe('76001333301320170009301');
});

it('filters processes by court', function (): void {
    $process1 = Process::factory()->create(['court' => 'JUZGADO 017 ADMINISTRATIVO']);
    $process2 = Process::factory()->create(['court' => 'JUZGADO 006 CIVIL']);

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?court=017');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.court'))->toContain('017');
});

it('filters processes by process_class', function (): void {
    $process1 = Process::factory()->create(['process_class' => 'ACCION DE REPARACION DIRECTA']);
    $process2 = Process::factory()->create(['process_class' => 'ACCION DE TUTELA']);

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?process_class=REPARACION');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.process_class'))->toContain('Reparacion');
});

it('filters processes by plaintiff', function (): void {
    $process1 = Process::factory()->create();
    $process2 = Process::factory()->create();

    ProcessSubject::factory()->forProcess($process1)->create([
        'subject_registration_id' => 12345678,
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ GARCIA',
        'identification' => '1234567890',
        'is_cited' => false,
    ]);

    ProcessSubject::factory()->forProcess($process2)->create([
        'subject_registration_id' => 87654321,
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'MARIA LOPEZ RODRIGUEZ',
        'identification' => '0987654321',
        'is_cited' => false,
    ]);

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?plaintiff=JUAN');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($process1->id);
});

it('filters processes by plaintiff using identification', function (): void {
    $process1 = Process::factory()->create();
    $process2 = Process::factory()->create();

    ProcessSubject::factory()->forProcess($process1)->create([
        'subject_registration_id' => 12345678,
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ GARCIA',
        'identification' => '1234567890',
        'is_cited' => false,
    ]);

    ProcessSubject::factory()->forProcess($process2)->create([
        'subject_registration_id' => 87654321,
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'MARIA LOPEZ RODRIGUEZ',
        'identification' => '0987654321',
        'is_cited' => false,
    ]);

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?plaintiff=1234567890');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($process1->id);
});

it('filters processes by defendant', function (): void {
    $process1 = Process::factory()->create();
    $process2 = Process::factory()->create();

    ProcessSubject::factory()->forProcess($process1)->create([
        'subject_registration_id' => 11111111,
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA ABC S.A.S.',
        'identification' => '9001234567',
        'is_cited' => false,
    ]);

    ProcessSubject::factory()->forProcess($process2)->create([
        'subject_registration_id' => 22222222,
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA XYZ LTDA.',
        'identification' => '9007654321',
        'is_cited' => false,
    ]);

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?defendant=ABC');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($process1->id);
});

it('filters processes by defendant using identification', function (): void {
    $process1 = Process::factory()->create();
    $process2 = Process::factory()->create();

    ProcessSubject::factory()->forProcess($process1)->create([
        'subject_registration_id' => 11111111,
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA ABC S.A.S.',
        'identification' => '9001234567',
        'is_cited' => false,
    ]);

    ProcessSubject::factory()->forProcess($process2)->create([
        'subject_registration_id' => 22222222,
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA XYZ LTDA.',
        'identification' => '9007654321',
        'is_cited' => false,
    ]);

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?defendant=9001234567');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($process1->id);
});

it('filters processes by last_activity_date date range', function (): void {
    $process1 = Process::factory()->create([
        'last_activity_date' => '2024-01-10',
    ]);
    $process2 = Process::factory()->create([
        'last_activity_date' => '2024-01-20',
    ]);
    $process3 = Process::factory()->create([
        'last_activity_date' => '2024-02-01',
    ]);

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process3->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?last_api_update_from=2024-01-15&last_api_update_to=2024-01-25');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($process2->id);
});

it('filters processes by status', function (): void {
    $process1 = Process::factory()->create();
    $process2 = Process::factory()->create();

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?status=active');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($process1->id);
    expect($response->json('data.0.status_label'))->toBe(__('enums.process_status.active'));
});

it('filters processes by has_multiple_instances', function (): void {
    $process1 = Process::factory()->create(['has_multiple_instances' => true]);
    $process2 = Process::factory()->create(['has_multiple_instances' => false]);

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?has_multiple_instances=true');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.has_multiple_instances'))->toBeTrue();
});

it('filters processes by created_at date', function (): void {
    $process1 = Process::factory()->create();
    $process2 = Process::factory()->create();

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?created_at='.now()->format('Y-m-d'));

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($process2->id);
});

it('filters processes by process_date', function (): void {
    $process1 = Process::factory()->create(['process_date' => '2024-01-15']);
    $process2 = Process::factory()->create(['process_date' => '2024-02-20']);

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?process_date=2024-01-15');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.process_date'))->toBe(\Src\Application\Shared\Helpers\DateFormatHelper::formatDate(\Illuminate\Support\Carbon::parse('2024-01-15')));
});

it('filters processes by created_at date range', function (): void {
    $process1 = Process::factory()->create();
    $process2 = Process::factory()->create();
    $process3 = Process::factory()->create();

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'created_at' => '2024-01-10 10:00:00',
        'updated_at' => '2024-01-10 10:00:00',
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'created_at' => '2024-01-20 10:00:00',
        'updated_at' => '2024-01-20 10:00:00',
    ]);
    $process3->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'created_at' => '2024-02-01 10:00:00',
        'updated_at' => '2024-02-01 10:00:00',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?created_at_from=2024-01-15&created_at_to=2024-01-25');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($process2->id);
});

it('filters processes by process_date date range', function (): void {
    $process1 = Process::factory()->create(['process_date' => '2024-01-10']);
    $process2 = Process::factory()->create(['process_date' => '2024-01-20']);
    $process3 = Process::factory()->create(['process_date' => '2024-02-01']);

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process3->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?process_date_from=2024-01-15&process_date_to=2024-01-25');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($process2->id);
});

it('returns paginated results', function (): void {
    // Create 25 processes
    $processes = Process::factory()->count(25)->create();

    foreach ($processes as $process) {
        $process->organizations()->attach($this->organization->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
        ]);
    }

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?per_page=10');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(10);
    expect($response->json('per_page'))->toBe(10);
    expect($response->json('total'))->toBe(25);
    expect($response->json('last_page'))->toBe(3);
});

it('includes index field that continues across pages', function (): void {
    // Create 25 processes
    $processes = Process::factory()->count(25)->create();

    foreach ($processes as $process) {
        $process->organizations()->attach($this->organization->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
        ]);
    }

    // First page (per_page=10)
    $responsePage1 = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?per_page=10&page=1');

    $responsePage1->assertStatus(200);
    expect($responsePage1->json('data.0.index'))->toBe(1);
    expect($responsePage1->json('data.9.index'))->toBe(10);

    // Second page
    $responsePage2 = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?per_page=10&page=2');

    $responsePage2->assertStatus(200);
    expect($responsePage2->json('data.0.index'))->toBe(11);
    expect($responsePage2->json('data.9.index'))->toBe(20);

    // Third page
    $responsePage3 = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?per_page=10&page=3');

    $responsePage3->assertStatus(200);
    expect($responsePage3->json('data.0.index'))->toBe(21);
    expect($responsePage3->json('data.4.index'))->toBe(25);
});

it('returns correct resource structure', function (): void {
    $process = Process::factory()->create([
        'process_number' => '76001333301320170009301',
        'court' => 'JUZGADO 017 ADMINISTRATIVO',
        'department' => 'VALLE DEL CAUCA',
        'process_type' => 'Ordinario',
        'process_class' => 'ACCION DE REPARACION DIRECTA',
        'is_private' => false,
        'has_multiple_instances' => true,
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'index',
                'id',
                'process_number',
                'court',
                'process_class',
                'subclass_process',
                'process_date',
                'last_activity_date',
                'is_private',
                'has_multiple_instances',
                'status_label',
                'created_at',
                'term_start_date',
                'term_end_date',
                'plaintiff',
                'defendant',
                'plaintiffs',
                'defendants',
                'instances' => [
                    '*' => [
                        'index',
                        'id',
                        'process_number',
                        'court',
                        'process_class',
                        'subclass_process',
                        'process_date',
                        'last_activity_date',
                        'is_private',
                        'has_multiple_instances',
                        'status_label',
                        'created_at',
                        'term_start_date',
                        'term_end_date',
                        'plaintiff',
                        'defendant',
                        'plaintiffs',
                        'defendants',
                    ],
                ],
            ],
        ],
        'current_page',
        'per_page',
        'total',
    ]);
});

it('converts court and process_class to title case', function (): void {
    $process = Process::factory()->create([
        'court' => 'JUZGADO 017 ADMINISTRATIVO DE CALI',
        'process_class' => 'ACCION DE REPARACION DIRECTA',
        'subclass_process' => 'SIN SUBCLASE DE PROCESO',
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes');

    $response->assertStatus(200);
    expect($response->json('data.0.court'))->toBe('Juzgado 017 Administrativo de Cali');
    expect($response->json('data.0.process_class'))->toBe('Accion de Reparacion Directa');
    expect($response->json('data.0.subclass_process'))->toBe('Sin Subclase de Proceso');
});

it('returns one row per radicado with instances array for multiple instances', function (): void {
    $processNumber = '76001400301020180007600';
    $process1 = Process::factory()->create([
        'process_number' => $processNumber,
        'court' => 'Juzgado 006 Civil Municipal de Ejecución de Sentencias de Cali',
        'process_class' => 'Ejecutivo Singular',
        'last_activity_date' => now()->subDays(2),
    ]);
    $process2 = Process::factory()->create([
        'process_number' => $processNumber,
        'court' => 'Juzgado 010 Civil Municipal de Cali',
        'process_class' => 'Ejecutivo Singular',
        'last_activity_date' => now(),
    ]);

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('total'))->toBe(1);
    expect($response->json('data.0.process_number'))->toBe($processNumber);
    expect($response->json('data.0.instances'))->toHaveCount(2);
    expect($response->json('data.0.id'))->toBe($process2->id);
    expect($response->json('data.0.court'))->toContain('010');
    expect($response->json('data.0.instances.0.id'))->toBe($process2->id);
    expect($response->json('data.0.instances.1.id'))->toBe($process1->id);
});

it('returns error when user has no organization', function (): void {
    $userWithoutOrg = AppUser::factory()->create([
        'email' => 'noorg@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($userWithoutOrg)
        ->getJson('/api/app-user/processes');

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('process.user_has_no_organization')],
        'code' => 422,
    ]);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;

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

it('filters processes by is_private', function (): void {
    $process1 = Process::factory()->create(['is_private' => true]);
    $process2 = Process::factory()->create(['is_private' => false]);

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes?is_private=true');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.is_private'))->toBeTrue();
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
    expect($response->json('data.0.process_date'))->toBe('2024-01-15');
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
                'id',
                'process_number',
                'court',
                'department',
                'process_type',
                'process_class',
                'subclass_process',
                'process_date',
                'last_activity_date',
                'location',
                'is_private',
                'has_multiple_instances',
                'status_label',
                'created_at',
            ],
        ],
        'current_page',
        'per_page',
        'total',
    ]);
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

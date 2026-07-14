<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create([
        'email' => 'instances@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);
});

it('requires authentication to list process instances', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->getJson("/api/app-user/processes/{$process->id}/instances");

    $response->assertStatus(401);
});

it('returns 422 when app user has no organization', function (): void {
    $userWithoutOrg = AppUser::factory()->create([
        'email' => 'noorg-instances@example.com',
        'email_verified_at' => now(),
    ]);

    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($userWithoutOrg)
        ->getJson("/api/app-user/processes/{$process->id}/instances");

    $response->assertStatus(422);
});

it('returns 404 when process does not exist', function (): void {
    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes/non-existent-id/instances');

    $response->assertStatus(404);
});

it('returns 404 when process belongs to a different organization', function (): void {
    $otherOrganization = Organization::factory()->create();
    $process = Process::factory()->create();
    $process->organizations()->attach($otherOrganization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}/instances");

    $response->assertStatus(404);
});

it('returns a single instance when process has no siblings', function (): void {
    $process = Process::factory()->create([
        'process_number' => '76001333301320170009301',
        'court' => 'JUZGADO 014 ADMINISTRATIVO DE CALI',
        'last_api_update' => now(),
        'has_multiple_instances' => false,
    ]);
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}/instances");

    $response->assertStatus(200);
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.id', $process->id);
    $response->assertJsonStructure([
        '*' => ['id', 'court', 'actions_count', 'last_api_update', 'status_label'],
    ]);
});

it('returns all instances for a multi-instance process', function (): void {
    $processNumber = '11001400303520160089003';

    $instance1 = Process::factory()->create([
        'process_number' => $processNumber,
        'court' => 'JUZGADO 006 CIVIL MUNICIPAL DE EJECUCION',
        'last_activity_date' => now()->subDays(1),
        'has_multiple_instances' => true,
    ]);
    $instance2 = Process::factory()->create([
        'process_number' => $processNumber,
        'court' => 'JUZGADO 010 CIVIL MUNICIPAL DE CALI',
        'last_activity_date' => now()->subDays(30),
        'has_multiple_instances' => true,
    ]);
    $instance3 = Process::factory()->create([
        'process_number' => $processNumber,
        'court' => 'JUZGADO 035 CIVIL MUNICIPAL DE BOGOTA',
        'last_activity_date' => now()->subDays(90),
        'has_multiple_instances' => true,
    ]);

    foreach ([$instance1, $instance2, $instance3] as $instance) {
        $instance->organizations()->attach($this->organization->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
        ]);
    }

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$instance1->id}/instances");

    $response->assertStatus(200);
    $response->assertJsonCount(3);

    $ids = collect($response->json())->pluck('id')->all();
    expect($ids)->toContain($instance1->id)
        ->and($ids)->toContain($instance2->id)
        ->and($ids)->toContain($instance3->id);
});

it('returns correct actions_count per instance', function (): void {
    $processNumber = '76001333301320200001234';

    $instance1 = Process::factory()->create([
        'process_number' => $processNumber,
        'has_multiple_instances' => true,
    ]);
    $instance2 = Process::factory()->create([
        'process_number' => $processNumber,
        'has_multiple_instances' => true,
    ]);

    ProcessAction::factory()->count(5)->create(['process_id' => $instance1->id]);
    ProcessAction::factory()->count(2)->create(['process_id' => $instance2->id]);

    foreach ([$instance1, $instance2] as $instance) {
        $instance->organizations()->attach($this->organization->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
        ]);
    }

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$instance1->id}/instances");

    $response->assertStatus(200);

    $data = collect($response->json());
    expect($data->firstWhere('id', $instance1->id)['actions_count'])->toBe(5);
    expect($data->firstWhere('id', $instance2->id)['actions_count'])->toBe(2);
});

it('orders instances by last_activity_date descending', function (): void {
    $processNumber = '76001333301320200009876';

    $oldest = Process::factory()->create([
        'process_number' => $processNumber,
        'last_activity_date' => now()->subDays(100),
        'has_multiple_instances' => true,
    ]);
    $newest = Process::factory()->create([
        'process_number' => $processNumber,
        'last_activity_date' => now()->subDays(1),
        'has_multiple_instances' => true,
    ]);
    $middle = Process::factory()->create([
        'process_number' => $processNumber,
        'last_activity_date' => now()->subDays(50),
        'has_multiple_instances' => true,
    ]);

    foreach ([$oldest, $newest, $middle] as $instance) {
        $instance->organizations()->attach($this->organization->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
        ]);
    }

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$oldest->id}/instances");

    $response->assertStatus(200);

    $ids = collect($response->json())->pluck('id')->all();
    expect($ids[0])->toBe($newest->id)
        ->and($ids[1])->toBe($middle->id)
        ->and($ids[2])->toBe($oldest->id);
});

it('does not include instances from other organizations', function (): void {
    $processNumber = '76001333301320200005555';
    $otherOrganization = Organization::factory()->create();

    $myInstance = Process::factory()->create([
        'process_number' => $processNumber,
        'has_multiple_instances' => true,
    ]);
    $otherInstance = Process::factory()->create([
        'process_number' => $processNumber,
        'has_multiple_instances' => true,
    ]);

    $myInstance->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $otherInstance->organizations()->attach($otherOrganization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$myInstance->id}/instances");

    $response->assertStatus(200);
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.id', $myInstance->id);
});

it('returns correct status_label per instance', function (): void {
    $processNumber = '76001333301320200006666';

    $activeInstance = Process::factory()->create([
        'process_number' => $processNumber,
        'has_multiple_instances' => true,
    ]);
    $inactiveInstance = Process::factory()->create([
        'process_number' => $processNumber,
        'has_multiple_instances' => true,
    ]);

    $activeInstance->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => OrganizationProcessStatus::ACTIVE->value,
    ]);
    $inactiveInstance->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => false,
        'status' => OrganizationProcessStatus::INACTIVE->value,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$activeInstance->id}/instances");

    $response->assertStatus(200);

    $data = collect($response->json());
    expect($data->firstWhere('id', $activeInstance->id)['status_label'])
        ->toBe(__('enums.organization_process_status.active'));
    expect($data->firstWhere('id', $inactiveInstance->id)['status_label'])
        ->toBe(__('enums.organization_process_status.inactive'));
});

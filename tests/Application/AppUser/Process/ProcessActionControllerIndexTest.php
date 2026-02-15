<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

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

it('requires authentication to list process actions', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->getJson("/api/app-user/processes/{$process->id}/actions");

    $response->assertStatus(401);
});

it('returns empty list when process has no actions', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}/actions");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data',
        'current_page',
        'per_page',
        'total',
    ]);
    expect($response->json('data'))->toBeEmpty();
});

it('returns process actions for a process', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $action1 = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_registration_id' => 123456,
        'action_date' => '2024-01-15',
        'action' => 'Test action 1',
        'annotation' => 'Test annotation 1',
        'registration_date' => '2024-01-15',
    ]);

    $action2 = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_registration_id' => 123457,
        'action_date' => '2024-01-20',
        'action' => 'Test action 2',
        'annotation' => 'Test annotation 2',
        'registration_date' => '2024-01-20',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}/actions");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('total'))->toBe(2);
    expect($response->json('data.0.id'))->toBe($action2->id); // Ordered by action_date desc
    expect($response->json('data.1.id'))->toBe($action1->id);
});

it('returns 404 when process does not exist', function (): void {
    $nonExistentId = '00000000-0000-0000-0000-000000000000';

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$nonExistentId}/actions");

    $response->assertStatus(404);
});

it('returns 404 when process belongs to another organization', function (): void {
    $otherOrganization = Organization::factory()->create();
    $process = Process::factory()->create();
    $process->organizations()->attach($otherOrganization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}/actions");

    $response->assertStatus(404);
});

it('returns error when user has no organization', function (): void {
    $userWithoutOrg = AppUser::factory()->create([
        'email' => 'noorg@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);

    $process = Process::factory()->create();

    $response = $this->actingAs($userWithoutOrg)
        ->getJson("/api/app-user/processes/{$process->id}/actions");

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('process.user_has_no_organization')],
        'code' => 422,
    ]);
});

it('filters actions by action_date_from', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => '2024-01-10',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => '2024-01-20',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => '2024-02-01',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}/actions?action_date_from=2024-01-15");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0.action_date'))->toBe(\Src\Application\Shared\Helpers\DateFormatHelper::formatDate(\Illuminate\Support\Carbon::parse('2024-02-01')));
    expect($response->json('data.1.action_date'))->toBe(\Src\Application\Shared\Helpers\DateFormatHelper::formatDate(\Illuminate\Support\Carbon::parse('2024-01-20')));
});

it('filters actions by action_date_to', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => '2024-01-10',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => '2024-01-20',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => '2024-02-01',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}/actions?action_date_to=2024-01-25");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0.action_date'))->toBe(\Src\Application\Shared\Helpers\DateFormatHelper::formatDate(\Illuminate\Support\Carbon::parse('2024-01-20')));
    expect($response->json('data.1.action_date'))->toBe(\Src\Application\Shared\Helpers\DateFormatHelper::formatDate(\Illuminate\Support\Carbon::parse('2024-01-10')));
});

it('filters actions by action_date range', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => '2024-01-10',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => '2024-01-20',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => '2024-02-01',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}/actions?action_date_from=2024-01-15&action_date_to=2024-01-25");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.action_date'))->toBe(\Src\Application\Shared\Helpers\DateFormatHelper::formatDate(\Illuminate\Support\Carbon::parse('2024-01-20')));
});

it('filters actions by registration_date_from', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => '2024-01-10',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => '2024-01-20',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => '2024-02-01',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}/actions?registration_date_from=2024-01-15");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('filters actions by registration_date_to', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => '2024-01-10',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => '2024-01-20',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => '2024-02-01',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}/actions?registration_date_to=2024-01-25");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('filters actions by registration_date range', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => '2024-01-10',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => '2024-01-20',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => '2024-02-01',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}/actions?registration_date_from=2024-01-15&registration_date_to=2024-01-25");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

it('filters actions by search term in action field', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'APELACION DE SENTENCIA',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'CONSULTA DE PRUEBAS',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'AUTO DE TRAMITE',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}/actions?search=APELACION");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.action'))->toBe('APELACION DE SENTENCIA');
});

it('filters actions by search term in annotation field', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Test action',
        'annotation' => 'Se abrió período de CONSULTA',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Test action 2',
        'annotation' => 'Se notificó al demandado',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}/actions?search=CONSULTA");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.annotation'))->toContain('CONSULTA');
});

it('returns paginated results', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    // Create 25 actions
    ProcessAction::factory()->count(25)->create([
        'process_id' => $process->id,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}/actions?per_page=10");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(10);
    expect($response->json('per_page'))->toBe(10);
    expect($response->json('total'))->toBe(25);
    expect($response->json('last_page'))->toBe(3);
});

it('returns correct resource structure', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_registration_id' => 123456,
        'action_date' => '2024-01-15',
        'action' => 'Test action',
        'annotation' => 'Test annotation',
        'start_date' => '2024-01-10',
        'end_date' => '2024-01-20',
        'registration_date' => '2024-01-15',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}/actions");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'action_registration_id',
                'action_date',
                'action',
                'annotation',
                'start_date',
                'end_date',
                'registration_date',
            ],
        ],
        'current_page',
        'per_page',
        'total',
    ]);
});

it('formats dates correctly', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => '2024-01-15',
        'start_date' => '2024-01-10',
        'end_date' => '2024-01-20',
        'registration_date' => '2024-01-15',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}/actions");

    $response->assertStatus(200);
    expect($response->json('data.0.action_date'))->toBe(\Src\Application\Shared\Helpers\DateFormatHelper::formatDate(\Illuminate\Support\Carbon::parse('2024-01-15')));
    expect($response->json('data.0.start_date'))->toBe(\Src\Application\Shared\Helpers\DateFormatHelper::formatDate(\Illuminate\Support\Carbon::parse('2024-01-10')));
    expect($response->json('data.0.end_date'))->toBe(\Src\Application\Shared\Helpers\DateFormatHelper::formatDate(\Illuminate\Support\Carbon::parse('2024-01-20')));
    expect($response->json('data.0.registration_date'))->toBe(\Src\Application\Shared\Helpers\DateFormatHelper::formatDate(\Illuminate\Support\Carbon::parse('2024-01-15')));
});

it('handles nullable fields correctly', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'annotation' => null,
        'start_date' => null,
        'end_date' => null,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process->id}/actions");

    $response->assertStatus(200);
    expect($response->json('data.0.annotation'))->toBeNull();
    expect($response->json('data.0.start_date'))->toBe('-');
    expect($response->json('data.0.end_date'))->toBe('-');
});

it('only returns actions for the specified process', function (): void {
    $process1 = Process::factory()->create();
    $process2 = Process::factory()->create();

    $process1->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $process2->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process1->id,
        'action' => 'Action for process 1',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process2->id,
        'action' => 'Action for process 2',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$process1->id}/actions");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.action'))->toBe('Action for process 1');
});

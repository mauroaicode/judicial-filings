<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ManualRegistrationRequestReason;
use Src\Domain\Process\Enums\ManualRegistrationRequestStatus;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\ManualRegistrationRequest;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    ManualRegistrationRequest::query()->delete();

    $this->user = User::factory()->create([
        'email' => 'admin-manual-reg@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create(['name' => 'Org Alpha']);
    $this->otherOrganization = Organization::factory()->create(['name' => 'Org Beta']);

    $this->appUser = AppUser::factory()->create([
        'name' => 'Ana',
        'last_name' => 'Lopez',
        'identification' => '99887766',
        'email' => 'ana.lopez@example.com',
    ]);
});

it('requires authentication for admin manual registration queue', function (): void {
    $this->getJson('/api/admin/processes/manual-registration-requests')
        ->assertStatus(401);
});

it('lists pending requests across organizations by default', function (): void {
    ManualRegistrationRequest::query()->create([
        'organization_id' => $this->organization->id,
        'app_user_id' => $this->appUser->id,
        'process_number' => '76892400300120260066300',
        'reason' => ManualRegistrationRequestReason::NotFound,
        'status' => ManualRegistrationRequestStatus::Pending,
        'lawyer_role' => ProcessLawyerRole::PLAINTIFF,
        'unassigned_actions_count' => 1,
        'discord_notified' => true,
    ]);

    ManualRegistrationRequest::query()->create([
        'organization_id' => $this->otherOrganization->id,
        'app_user_id' => $this->appUser->id,
        'process_number' => '76892400300120260066301',
        'reason' => ManualRegistrationRequestReason::Private,
        'status' => ManualRegistrationRequestStatus::Pending,
        'unassigned_actions_count' => 0,
        'discord_notified' => true,
    ]);

    ManualRegistrationRequest::query()->create([
        'organization_id' => $this->organization->id,
        'app_user_id' => $this->appUser->id,
        'process_number' => '76892400300120260066302',
        'reason' => ManualRegistrationRequestReason::NotFound,
        'status' => ManualRegistrationRequestStatus::Registered,
        'unassigned_actions_count' => 0,
        'discord_notified' => true,
        'resolved_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/processes/manual-registration-requests');

    $response->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'process_number',
                    'reason',
                    'reason_label',
                    'status',
                    'organization_id',
                    'organization_name',
                    'requested_by_name',
                    'unassigned_actions_count',
                    'created_at',
                ],
            ],
        ]);
});

it('filters by organization name', function (): void {
    ManualRegistrationRequest::query()->create([
        'organization_id' => $this->organization->id,
        'app_user_id' => $this->appUser->id,
        'process_number' => '76892400300120260066300',
        'reason' => ManualRegistrationRequestReason::NotFound,
        'status' => ManualRegistrationRequestStatus::Pending,
        'unassigned_actions_count' => 0,
        'discord_notified' => true,
    ]);
    ManualRegistrationRequest::query()->create([
        'organization_id' => $this->otherOrganization->id,
        'app_user_id' => $this->appUser->id,
        'process_number' => '76892400300120260066301',
        'reason' => ManualRegistrationRequestReason::NotFound,
        'status' => ManualRegistrationRequestStatus::Pending,
        'unassigned_actions_count' => 0,
        'discord_notified' => true,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/admin/processes/manual-registration-requests?organization=Alpha')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.organization_name', 'Org Alpha');
});

it('marks a pending request as registered and inserts the process', function (): void {
    \Illuminate\Support\Facades\Notification::fake();

    $request = ManualRegistrationRequest::query()->create([
        'organization_id' => $this->organization->id,
        'app_user_id' => $this->appUser->id,
        'process_number' => '76892400300120260066300',
        'reason' => ManualRegistrationRequestReason::NotFound,
        'status' => ManualRegistrationRequestStatus::Pending,
        'lawyer_role' => ProcessLawyerRole::DEFENDANT,
        'process_class' => 'Verbal',
        'plaintiffs' => [['name' => 'Juan Pérez', 'identification' => '123']],
        'defendants' => [['name' => 'Empresa SA', 'identification' => null]],
        'unassigned_actions_count' => 0,
        'discord_notified' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/api/admin/processes/manual-registration-requests/{$request->id}", [
            'status' => 'registered',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'registered');

    expect($request->fresh()?->status)->toBe(ManualRegistrationRequestStatus::Registered)
        ->and($request->fresh()?->resolved_at)->not->toBeNull()
        ->and(\Src\Domain\Process\Models\Process::query()->whereProcessNumber('76892400300120260066300')->exists())->toBeTrue();
});

it('registers a pending request with edited subjects and creates the process', function (): void {
    \Illuminate\Support\Facades\Notification::fake();

    $request = ManualRegistrationRequest::query()->create([
        'organization_id' => $this->organization->id,
        'app_user_id' => $this->appUser->id,
        'process_number' => '76892400300120260066400',
        'reason' => ManualRegistrationRequestReason::Private,
        'status' => ManualRegistrationRequestStatus::Pending,
        'lawyer_role' => ProcessLawyerRole::PLAINTIFF,
        'process_class' => 'Ordinario',
        'plaintiffs' => [['name' => 'Mal escrito', 'identification' => null]],
        'defendants' => [['name' => 'Demandado original', 'identification' => null]],
        'unassigned_actions_count' => 0,
        'discord_notified' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/admin/processes/manual-registration-requests/{$request->id}/register", [
            'process_class' => 'Verbal sumario',
            'lawyer_role' => 'defendant',
            'court' => 'Juzgado 1 Civil',
            'speaker' => 'Maria Gomez',
            'subclass_process' => 'Singular',
            'location' => 'Despacho',
            'plaintiffs' => [['name' => 'Ana Corregida', 'identification' => '555']],
            'defendants' => [['name' => 'Empresa Corregida']],
            'other_subjects' => [['name' => 'Apoderado']],
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'registered')
        ->assertJsonPath('data.process_class', 'Verbal sumario')
        ->assertJsonPath('data.court', 'Juzgado 1 Civil')
        ->assertJsonPath('data.lawyer_role', 'defendant')
        ->assertJsonPath('data.plaintiffs.0.name', 'Ana Corregida');

    $process = \Src\Domain\Process\Models\Process::query()->whereProcessNumber('76892400300120260066400')->first();
    expect($process)->not->toBeNull()
        ->and($process?->is_manual_sync)->toBeTrue()
        ->and($process?->is_private)->toBeTrue()
        ->and($process?->process_class)->toBe('Verbal sumario')
        ->and($process?->court)->toBe('Juzgado 1 Civil')
        ->and($process?->speaker)->toBe('Maria Gomez')
        ->and($process?->subclass_process)->toBe('Singular')
        ->and($process?->location)->toBe('Despacho')
        ->and($process?->organizations()->where('organizations.id', $this->organization->id)->exists())->toBeTrue()
        ->and($process?->subjects()->where('name_or_business_name', 'Ana Corregida')->exists())->toBeTrue();
});

it('rejects resolving an already resolved request', function (): void {
    $request = ManualRegistrationRequest::query()->create([
        'organization_id' => $this->organization->id,
        'app_user_id' => $this->appUser->id,
        'process_number' => '76892400300120260066300',
        'reason' => ManualRegistrationRequestReason::NotFound,
        'status' => ManualRegistrationRequestStatus::Rejected,
        'unassigned_actions_count' => 0,
        'discord_notified' => true,
        'resolved_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/admin/processes/manual-registration-requests/{$request->id}", [
            'status' => 'registered',
        ])
        ->assertStatus(422);
});

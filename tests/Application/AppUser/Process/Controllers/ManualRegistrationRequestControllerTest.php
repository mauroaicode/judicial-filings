<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ManualRegistrationRequestReason;
use Src\Domain\Process\Enums\ManualRegistrationRequestStatus;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\ManualRegistrationRequest;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create([
        'email' => 'manual-req@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'name' => 'Carlos',
        'last_name' => 'Ruiz',
        'identification' => '111222333',
    ]);
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);
});

it('requires authentication to list pending manual registration requests', function (): void {
    $this->getJson('/api/app-user/processes/manual-registration-requests')
        ->assertStatus(401);
});

it('lists pending manual registration requests for the organization', function (): void {
    ManualRegistrationRequest::query()->create([
        'organization_id' => $this->organization->id,
        'app_user_id' => $this->appUser->id,
        'process_number' => '76892400300120260066300',
        'reason' => ManualRegistrationRequestReason::NotFound,
        'status' => ManualRegistrationRequestStatus::Pending,
        'lawyer_role' => ProcessLawyerRole::PLAINTIFF,
        'unassigned_actions_count' => 2,
        'discord_notified' => true,
    ]);

    ManualRegistrationRequest::query()->create([
        'organization_id' => $this->organization->id,
        'app_user_id' => $this->appUser->id,
        'process_number' => '76892400300120260066301',
        'reason' => ManualRegistrationRequestReason::Private,
        'status' => ManualRegistrationRequestStatus::Registered,
        'lawyer_role' => ProcessLawyerRole::DEFENDANT,
        'unassigned_actions_count' => 0,
        'discord_notified' => true,
        'resolved_at' => now(),
    ]);

    $otherOrg = Organization::factory()->create();
    ManualRegistrationRequest::query()->create([
        'organization_id' => $otherOrg->id,
        'app_user_id' => $this->appUser->id,
        'process_number' => '76892400300120260066302',
        'reason' => ManualRegistrationRequestReason::NotFound,
        'status' => ManualRegistrationRequestStatus::Pending,
        'unassigned_actions_count' => 0,
        'discord_notified' => false,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/processes/manual-registration-requests');

    $response->assertStatus(200)
        ->assertJsonPath('count', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.process_number', '76892400300120260066300')
        ->assertJsonPath('data.0.reason', 'not_found')
        ->assertJsonPath('data.0.unassigned_actions_count', 2)
        ->assertJsonPath('data.0.requested_by_name', 'Carlos Ruiz');
});

it('creates a pending request with details and notifies discord on submit', function (): void {
    config([
        'discord-alerts.webhook_urls.manual_registration' => 'https://discord.com/api/webhooks/123456789/abcdefghijklmnopqrstuvwxyz',
    ]);

    \Illuminate\Support\Facades\Queue::fake();

    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes/manual-registration-requests', [
            'process_number' => '76892400300120260066300',
            'reason' => 'private',
            'lawyer_role' => 'defendant',
            'process_class' => 'Verbal',
            'plaintiffs' => [
                ['name' => 'Juan Pérez', 'identification' => '123'],
            ],
            'defendants' => [
                ['name' => 'Empresa SA'],
            ],
            'other_subjects' => [
                ['name' => 'Apoderado X'],
            ],
        ]);

    $response->assertStatus(202)
        ->assertJsonPath('status', 'manual_review')
        ->assertJsonPath('reason', 'private')
        ->assertJsonPath('data.process_class', 'Verbal')
        ->assertJsonPath('data.plaintiffs.0.name', 'Juan Pérez')
        ->assertJsonPath('data.defendants.0.name', 'Empresa SA');

    expect($response->json('request_id'))->not->toBeEmpty();

    $this->assertDatabaseHas('manual_registration_requests', [
        'process_number' => '76892400300120260066300',
        'organization_id' => $this->organization->id,
        'status' => 'pending',
        'process_class' => 'Verbal',
        'discord_notified' => true,
    ]);
});

it('validates required details when submitting manual registration', function (): void {
    $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes/manual-registration-requests', [
            'process_number' => '76892400300120260066300',
            'reason' => 'private',
            'lawyer_role' => 'defendant',
            'process_class' => 'Verbal',
            'plaintiffs' => [],
            'defendants' => [],
        ])
        ->assertStatus(422);
});

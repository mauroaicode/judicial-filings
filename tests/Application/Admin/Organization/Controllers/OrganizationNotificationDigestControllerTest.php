<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessDataSource;
use Src\Domain\Process\Models\ProcessSubject;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    OrganizationNotification::query()->delete();
    NotificationDigest::query()->delete();

    $this->user = User::factory()->create([
        'email' => 'admin-org-digest@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);
    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create(['name' => 'Bufete Digest Admin']);
});

function attachActiveOrgProcess(Organization $org, ?Process $process = null): Process
{
    $process ??= Process::factory()->create();
    $process->organizations()->attach($org->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'lawyer_role' => 'plaintiff',
        'status' => \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus::ACTIVE->value,
    ]);

    return $process;
}

function createPendingOrgActuacion(Organization $org, ProcessAction $action): void
{
    OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $org->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
        'is_notified' => false,
        'is_email_notified' => false,
    ]);
}

it('requires authentication for pending digest details', function (): void {
    $this->getJson("/api/admin/organizations/{$this->organization->id}/notification-digests/pending")
        ->assertStatus(401);
});

it('forbids non-admin users for pending digest details', function (): void {
    $plain = User::factory()->create([
        'email' => 'plain-org-digest@example.com',
        'password' => Hash::make('p'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $this->actingAs($plain)
        ->getJson("/api/admin/organizations/{$this->organization->id}/notification-digests/pending")
        ->assertStatus(403);
});

it('returns empty pending digest details when organization has no pending actuaciones', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/organizations/{$this->organization->id}/notification-digests/pending");

    $response->assertOk();
    expect($response->json('data.0.organization_id'))->toBe($this->organization->id)
        ->and($response->json('data.0.organization_name'))->toBe('Bufete Digest Admin')
        ->and($response->json('data.0.is_pending'))->toBeTrue()
        ->and($response->json('data.0.actions_count'))->toBe(0)
        ->and($response->json('data.0.data'))->toBe([])
        ->and($response->json('data.0.id'))->toBeNull();
});

it('returns pending digest process and actuación details for admin drill-down', function (): void {
    $process = attachActiveOrgProcess($this->organization, Process::factory()->create([
        'process_number' => '76001333301320170009301',
        'court' => 'JUZGADO 007 CIVIL MUNICIPAL DE CALI',
        'last_activity_date' => now()->subDays(5),
    ]));

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'MARIA LOPEZ',
    ]);

    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Auto Decide',
        'annotation' => 'Traslado',
        'action_date' => now()->toDateString(),
        'registration_date' => now()->toDateString(),
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
    ]);

    createPendingOrgActuacion($this->organization, $action);

    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/organizations/{$this->organization->id}/notification-digests/pending?per_page=20");

    $response->assertOk();
    expect($response->json('data.0.is_pending'))->toBeTrue()
        ->and($response->json('data.0.actions_count'))->toBe(1)
        ->and($response->json('total'))->toBe(1);

    $item = $response->json('data.0.data.0');
    expect($item['process_number'])->toBe('76001333301320170009301')
        ->and($item['process_action_id'])->toBe($action->id)
        ->and($item['plaintiff'])->toBe('Juan Perez')
        ->and($item['defendant'])->toBe('Maria Lopez')
        ->and($item['action_text'])->toBe('Auto Decide')
        ->and($item['court'])->toContain('Juzgado 007 Civil Municipal')
        ->and($item['alert_level'])->toBe('green');
});

it('includes pending actuaciones regardless of process data source (rama or samai)', function (): void {
    $rama = ProcessDataSource::query()->firstOrCreate(
        ['slug' => 'judicial_branch'],
        ['name' => 'Rama Judicial', 'is_active' => true],
    );
    $samai = ProcessDataSource::query()->firstOrCreate(
        ['slug' => 'samai'],
        ['name' => 'Samai', 'is_active' => true],
    );

    $ramaProcess = attachActiveOrgProcess($this->organization, Process::factory()->create([
        'process_number' => '11001310300120240000100',
        'process_data_source_id' => $rama->id,
    ]));
    $samaiProcess = attachActiveOrgProcess($this->organization, Process::factory()->create([
        'process_number' => '11001310300120240000200',
        'process_data_source_id' => $samai->id,
    ]));

    $ramaAction = ProcessAction::factory()->create([
        'process_id' => $ramaProcess->id,
        'registration_date' => now()->toDateString(),
        'action' => 'Auto Rama',
    ]);
    $samaiAction = ProcessAction::factory()->create([
        'process_id' => $samaiProcess->id,
        'registration_date' => now()->toDateString(),
        'action' => 'Auto Samai',
    ]);

    createPendingOrgActuacion($this->organization, $ramaAction);
    createPendingOrgActuacion($this->organization, $samaiAction);

    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/organizations/{$this->organization->id}/notification-digests/pending");

    $response->assertOk();
    $numbers = collect($response->json('data.0.data'))->pluck('process_number')->all();

    expect($response->json('data.0.actions_count'))->toBe(2)
        ->and($numbers)->toEqualCanonicalizing([
            '11001310300120240000100',
            '11001310300120240000200',
        ]);
});

it('does not mark pending notifications as notified when viewing pending details', function (): void {
    $process = attachActiveOrgProcess($this->organization);
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => now()->toDateString(),
    ]);
    createPendingOrgActuacion($this->organization, $action);

    $this->actingAs($this->user)
        ->getJson("/api/admin/organizations/{$this->organization->id}/notification-digests/pending")
        ->assertOk();

    $notification = OrganizationNotification::query()
        ->where('organization_id', $this->organization->id)
        ->where('notifiable_id', $action->id)
        ->first();

    expect($notification)->not->toBeNull()
        ->and($notification->is_email_notified)->toBeFalse()
        ->and($notification->notification_digest_id)->toBeNull();
});

it('returns persisted digest details scoped to the organization', function (): void {
    $process = attachActiveOrgProcess($this->organization, Process::factory()->create([
        'process_number' => '76001333301320170009999',
    ]));
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => now()->toDateString(),
    ]);

    $digest = NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'data' => [
            [
                'process_number' => $process->process_number,
                'process_action_id' => $action->id,
                'action_text' => 'Auto Decide',
                'registration_date' => now()->format('Y-m-d'),
                'demandante' => 'Ana Ruiz',
                'demandado' => 'Banco X',
            ],
        ],
    ]);

    OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $this->organization->id,
        'notification_digest_id' => $digest->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
        'is_notified' => true,
        'is_email_notified' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/organizations/{$this->organization->id}/notification-digests/{$digest->id}");

    $response->assertOk();
    expect($response->json('data.0.id'))->toBe($digest->id)
        ->and($response->json('data.0.organization_id'))->toBe($this->organization->id)
        ->and($response->json('data.0.is_pending'))->toBeFalse()
        ->and($response->json('data.0.is_viewed'))->toBeFalse()
        ->and($response->json('data.0.viewed_at'))->toBeNull()
        ->and($response->json('data.0.actions_count'))->toBe(1)
        ->and($response->json('data.0.data.0.process_number'))->toBe($process->process_number);
});

it('includes whether the organization already opened the consolidado and when', function (): void {
    $viewedAt = '2030-01-16 09:30:00';

    $digest = NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'viewed_at' => $viewedAt,
        'data' => [
            [
                'process_number' => '76001333301320170008888',
                'action_text' => 'Auto',
                'registration_date' => '2030-01-16',
            ],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/admin/organizations/{$this->organization->id}/notification-digests/{$digest->id}");

    $response->assertOk()
        ->assertJsonPath('data.0.is_viewed', true)
        ->assertJsonPath('data.0.viewed_at', DateFormatHelper::formatDateWithTime($viewedAt));
});

it('returns 404 when persisted digest belongs to another organization', function (): void {
    $otherOrg = Organization::factory()->create();
    $digest = NotificationDigest::factory()->create([
        'organization_id' => $otherOrg->id,
        'data' => [],
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/admin/organizations/{$this->organization->id}/notification-digests/{$digest->id}")
        ->assertNotFound();
});

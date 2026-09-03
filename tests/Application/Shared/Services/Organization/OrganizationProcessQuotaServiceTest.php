<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Organization\Models\OrganizationSetting;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Models\Process;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->quota = app(OrganizationProcessQuotaService::class);

    $this->organization = Organization::factory()->create(['is_active' => true]);
    OrganizationSetting::factory()->create([
        'organization_id' => $this->organization->id,
        'max_active_processes' => 2,
    ]);
});

function attachActiveRadicado(Organization $org, string $processNumber): Process
{
    $process = Process::factory()->create(['process_number' => $processNumber]);
    $process->organizations()->attach($org->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => OrganizationProcessStatus::ACTIVE->value,
    ]);

    return $process;
}

it('counts distinct active radicados for an organization', function (): void {
    attachActiveRadicado($this->organization, '76001333301320170000100');
    attachActiveRadicado($this->organization, '76001333301320170000200');

    // Second instance of same radicado should not increase the count.
    $duplicate = Process::factory()->create(['process_number' => '76001333301320170000100']);
    $duplicate->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => OrganizationProcessStatus::ACTIVE->value,
    ]);

    expect($this->quota->countActiveProcesses($this->organization->id))->toBe(2)
        ->and($this->quota->resolveLimit($this->organization->id))->toBe(2)
        ->and($this->quota->remainingSlots($this->organization->id))->toBe(0);
});

it('allows adding when under the limit and blocks when at capacity', function (): void {
    attachActiveRadicado($this->organization, '76001333301320170001100');

    $this->quota->assertCanAddProcesses($this->organization->id, 1);

    attachActiveRadicado($this->organization, '76001333301320170001200');

    try {
        $this->quota->assertCanAddProcesses($this->organization->id, 1);
        expect(false)->toBeTrue();
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->getMessage())->toContain('límite');
    }
});

it('treats null max_active_processes as unlimited when no config default', function (): void {
    config(['organization.defaults.max_active_processes' => null]);

    $org = Organization::factory()->create();
    OrganizationSetting::factory()->create([
        'organization_id' => $org->id,
        'max_active_processes' => null,
    ]);

    expect($this->quota->resolveLimit($org->id))->toBeNull()
        ->and($this->quota->remainingSlots($org->id))->toBeNull();

    $this->quota->assertCanAddProcesses($org->id, 100);
});

it('falls back to config default when organization has no configured limit', function (): void {
    config(['organization.defaults.max_active_processes' => 50]);

    $org = Organization::factory()->create();
    OrganizationSetting::factory()->create([
        'organization_id' => $org->id,
        'max_active_processes' => null,
    ]);

    expect($this->quota->configuredMaxActiveProcesses($org->id))->toBeNull()
        ->and($this->quota->defaultMaxActiveProcesses())->toBe(50)
        ->and($this->quota->resolveLimit($org->id))->toBe(50);
});

it('blocks the next radicado using the .env default when org has no override', function (): void {
    config(['organization.defaults.max_active_processes' => 2]);

    $org = Organization::factory()->create();
    OrganizationSetting::factory()->create([
        'organization_id' => $org->id,
        'max_active_processes' => null, // sin configurar → usa default 2
    ]);

    attachActiveRadicado($org, '76001333301320170002100');
    attachActiveRadicado($org, '76001333301320170002200');

    expect($this->quota->resolveLimit($org->id))->toBe(2)
        ->and($this->quota->countActiveProcesses($org->id))->toBe(2);

    try {
        $this->quota->assertCanAddProcesses($org->id, 1); // el "3ro" / equivalente al 61 si default=60
        expect(false)->toBeTrue();
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->getMessage())->toContain('límite');
    }
});

it('admin can read and update organization settings', function (): void {
    config(['organization.defaults.max_active_processes' => 25]);

    $user = User::factory()->create([
        'email' => 'admin-org-settings@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);
    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $user->roles()->attach($adminRole->id);

    $this->actingAs($user)
        ->getJson("/api/admin/organizations/{$this->organization->id}/settings")
        ->assertOk()
        ->assertJsonPath('max_active_processes', 2)
        ->assertJsonPath('max_active_processes_configured', 2)
        ->assertJsonPath('default_max_active_processes', 25)
        ->assertJsonPath('active_processes_count', 0);

    $this->actingAs($user)
        ->putJson("/api/admin/organizations/{$this->organization->id}/settings", [
            'max_active_processes' => 10,
        ])
        ->assertOk()
        ->assertJsonPath('settings.max_active_processes', 10)
        ->assertJsonPath('settings.max_active_processes_configured', 10);

    expect(
        OrganizationSetting::query()
            ->where('organization_id', $this->organization->id)
            ->value('max_active_processes')
    )->toBe(10);

    // null configured → uses .env/config default
    $this->actingAs($user)
        ->putJson("/api/admin/organizations/{$this->organization->id}/settings", [
            'max_active_processes' => null,
        ])
        ->assertOk()
        ->assertJsonPath('settings.max_active_processes_configured', null)
        ->assertJsonPath('settings.max_active_processes', 25)
        ->assertJsonPath('settings.default_max_active_processes', 25);
});

it('creates settings row when organization is created', function (): void {
    $user = User::factory()->create([
        'email' => 'admin-org-create-settings@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);
    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $user->roles()->attach($adminRole->id);

    $response = $this->actingAs($user)
        ->postJson('/api/admin/organizations', [
            'name' => 'Org Con Settings',
            'type' => 'natural',
            'identification' => '1098765432',
            'phone' => '3001234567',
            'email' => 'org-settings-create@example.com',
            'generate_password' => true,
        ]);

    $response->assertCreated();
    $orgId = $response->json('id');

    expect(
        OrganizationSetting::query()->where('organization_id', $orgId)->exists()
    )->toBeTrue();
});

it('returns quota summary for app user ui', function (): void {
    config(['organization.defaults.max_active_processes' => 60]);

    OrganizationSetting::query()
        ->where('organization_id', $this->organization->id)
        ->update(['max_active_processes' => 46]);

    attachActiveRadicado($this->organization, '76001333301320170004100');

    $summary = $this->quota->getSummary($this->organization->id);

    expect($summary)->toBe([
        'active_processes_count' => 1,
        'max_active_processes' => 46,
        'remaining_slots' => 45,
        'is_unlimited' => false,
        'is_at_limit' => false,
        'can_add_process' => true,
    ]);
});

it('partitions radicados when import exceeds remaining quota', function (): void {
    attachActiveRadicado($this->organization, '76001333301320170001100');
    attachActiveRadicado($this->organization, '76001333301320170001200');

    $partition = $this->quota->partitionProcessNumbersByQuota($this->organization->id, [
        '76001333301320170001300',
        '76001333301320170001400',
        '76001333301320170001500',
    ]);

    expect($partition['allowed'])->toBe([])
        ->and($partition['rejected'])->toHaveCount(3)
        ->and($partition['rejected'][0]['process_number'])->toBe('76001333301320170001300')
        ->and($partition['rejected'][0]['reason'])->toContain('límite');
});

it('allows partial enqueue when import exceeds remaining quota', function (): void {
    attachActiveRadicado($this->organization, '76001333301320170002100');

    $partition = $this->quota->partitionProcessNumbersByQuota($this->organization->id, [
        '76001333301320170002200',
        '76001333301320170002300',
        '76001333301320170002400',
    ]);

    expect($partition['allowed'])->toBe(['76001333301320170002200'])
        ->and($partition['rejected'])->toHaveCount(2);
});

it('returns organization detail with nested settings for admin modal', function (): void {
    config(['organization.defaults.max_active_processes' => 60]);

    $user = User::factory()->create([
        'email' => 'admin-org-detail@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);
    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $user->roles()->attach($adminRole->id);

    OrganizationSetting::query()
        ->where('organization_id', $this->organization->id)
        ->update(['max_active_processes' => null]);

    attachActiveRadicado($this->organization, '76001333301320170003100');

    $this->actingAs($user)
        ->getJson("/api/admin/organizations/{$this->organization->id}")
        ->assertOk()
        ->assertJsonPath('id', $this->organization->id)
        ->assertJsonPath('name', $this->organization->name)
        ->assertJsonPath('type', $this->organization->type)
        ->assertJsonPath('active_processes_count', 1)
        ->assertJsonPath('settings.max_active_processes', 60)
        ->assertJsonPath('settings.max_active_processes_configured', null)
        ->assertJsonPath('settings.default_max_active_processes', 60)
        ->assertJsonPath('settings.remaining_slots', 59);
});

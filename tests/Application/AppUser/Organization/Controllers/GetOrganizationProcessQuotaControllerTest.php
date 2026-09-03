<?php

declare(strict_types=1);

namespace Tests\Application\AppUser\Organization\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Organization\Models\OrganizationSetting;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Models\Process;
use Tests\TestCase;

class GetOrganizationProcessQuotaControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/app-user/process-quota')
            ->assertUnauthorized();
    }

    public function test_it_returns_quota_summary_for_authenticated_user(): void
    {
        config(['organization.defaults.max_active_processes' => 60]);

        $organization = Organization::factory()->create(['is_active' => true]);
        OrganizationSetting::factory()->create([
            'organization_id' => $organization->id,
            'max_active_processes' => 46,
        ]);

        $process = Process::factory()->create(['process_number' => '76001333301320170000100']);
        $process->organizations()->attach($organization->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
            'status' => OrganizationProcessStatus::ACTIVE->value,
        ]);

        $appUser = AppUser::factory()->create();
        $appUser->organizations()->attach($organization->id);

        $this->actingAs($appUser, 'sanctum')
            ->getJson('/api/app-user/process-quota')
            ->assertOk()
            ->assertJson([
                'active_processes_count' => 1,
                'max_active_processes' => 46,
                'remaining_slots' => 45,
                'is_unlimited' => false,
                'is_at_limit' => false,
                'can_add_process' => true,
            ]);
    }

    public function test_it_reports_when_organization_is_at_limit(): void
    {
        $organization = Organization::factory()->create(['is_active' => true]);
        OrganizationSetting::factory()->create([
            'organization_id' => $organization->id,
            'max_active_processes' => 1,
        ]);

        $process = Process::factory()->create(['process_number' => '76001333301320170000200']);
        $process->organizations()->attach($organization->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
            'status' => OrganizationProcessStatus::ACTIVE->value,
        ]);

        $appUser = AppUser::factory()->create();
        $appUser->organizations()->attach($organization->id);

        $this->actingAs($appUser, 'sanctum')
            ->getJson('/api/app-user/process-quota')
            ->assertOk()
            ->assertJson([
                'active_processes_count' => 1,
                'max_active_processes' => 1,
                'remaining_slots' => 0,
                'is_unlimited' => false,
                'is_at_limit' => true,
                'can_add_process' => false,
            ]);
    }

    public function test_it_returns_unlimited_when_no_effective_limit(): void
    {
        config(['organization.defaults.max_active_processes' => null]);

        $organization = Organization::factory()->create(['is_active' => true]);
        OrganizationSetting::factory()->create([
            'organization_id' => $organization->id,
            'max_active_processes' => null,
        ]);

        $appUser = AppUser::factory()->create();
        $appUser->organizations()->attach($organization->id);

        $this->actingAs($appUser, 'sanctum')
            ->getJson('/api/app-user/process-quota')
            ->assertOk()
            ->assertJson([
                'active_processes_count' => 0,
                'max_active_processes' => null,
                'remaining_slots' => null,
                'is_unlimited' => true,
                'is_at_limit' => false,
                'can_add_process' => true,
            ]);
    }
}

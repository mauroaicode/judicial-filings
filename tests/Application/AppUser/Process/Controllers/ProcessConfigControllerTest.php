<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create([
        'password' => Hash::make('password1234'),
    ]);

    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);
});

it('can update multiple processes configuration and calculate alert levels', function (): void {
    // 1. Create processes with different inactivity dates
    // Case 1: Red (Plaintiff >= 90 days)
    $processRed = Process::factory()->create([
        'last_activity_date' => Date::now()->subDays(100)->toDateString(),
    ]);

    // Case 2: Yellow (Plaintiff >= 45 days)
    $processYellow = Process::factory()->create([
        'last_activity_date' => Date::now()->subDays(50)->toDateString(),
    ]);

    // Case 3: Green (Plaintiff < 45 days)
    $processGreenPlaintiff = Process::factory()->create([
        'last_activity_date' => Date::now()->subDays(10)->toDateString(),
    ]);

    // Attach all to organization
    foreach ([$processRed, $processYellow, $processGreenPlaintiff] as $p) {
        $p->organizations()->attach($this->organization->id, [
            'is_active' => true,
            'interest_date' => now()->toDateString(),
        ]);
    }

    $response = $this->actingAs($this->appUser)
        ->patchJson('/api/app-user/processes/bulk-config/roles', [
            'process_ids' => [$processRed->id, $processYellow->id, $processGreenPlaintiff->id],
            'lawyer_role' => 'plaintiff',
        ]);

    $response->assertStatus(200);
    $response->assertJson([
        'total_updated' => 3,
        'red_alerts' => [
            'count' => 1,
            'process_ids' => [$processRed->id],
        ],
        'yellow_alerts' => [
            'count' => 1,
            'process_ids' => [$processYellow->id],
        ],
        'green_alerts' => [
            'count' => 1,
            'process_ids' => [$processGreenPlaintiff->id],
        ],
    ]);

    // Verify DB state
    $this->assertDatabaseHas('organization_processes', [
        'process_id' => $processGreenPlaintiff->id,
        'lawyer_role' => 'plaintiff',
        'inactivity_alert_level' => 'green',
    ]);
});

it('calculates green level for defendants correctly', function (): void {
    $processGreen = Process::factory()->create([
        'last_activity_date' => Date::now()->subDays(100)->toDateString(),
    ]);
    $processGreen->organizations()->attach($this->organization->id, [
        'is_active' => true,
        'interest_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->appUser)
        ->patchJson('/api/app-user/processes/bulk-config/roles', [
            'process_ids' => [$processGreen->id],
            'lawyer_role' => 'defendant',
        ]);

    $response->assertStatus(200);
    $response->assertJson([
        'green_alerts' => [
            'count' => 1,
            'process_ids' => [$processGreen->id],
        ],
    ]);
});

it('validates required fields for bulk update', function (): void {
    $response = $this->actingAs($this->appUser)
        ->patchJson('/api/app-user/processes/bulk-config/roles', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['process_ids', 'lawyer_role']);
});

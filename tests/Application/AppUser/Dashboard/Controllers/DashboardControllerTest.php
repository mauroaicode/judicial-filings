<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create([
        'email' => 'dashboard@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);

    // Attach user to organization
    $this->appUser->organizations()->attach($this->organization->id, [
        'is_owner' => true,
    ]);
});

it('returns dashboard summary with correct kpi counts', function (): void {
    // 1. Create a process with Red alert level
    $processRed = Process::factory()->create();
    $processRed->organizations()->attach($this->organization->id, [
        'inactivity_alert_level' => 'red',
        'is_active' => true,
        'interest_date' => now(),
    ]);

    // 2. Create actions with different dates
    // One inside range
    $actionToday = ProcessAction::factory()->create([
        'process_id' => $processRed->id,
        'action_date' => Carbon::now()->toDateString(),
    ]);

    // One outside range (yesterday)
    ProcessAction::factory()->create([
        'process_id' => $processRed->id,
        'action_date' => Carbon::now()->subDay()->toDateString(),
    ]);

    // 3. Create another process with Yellow level
    $processYellow = Process::factory()->create();
    $processYellow->organizations()->attach($this->organization->id, [
        'inactivity_alert_level' => 'yellow',
        'is_active' => true,
        'interest_date' => now(),
    ]);

    // Request for today
    $today = Carbon::now()->toDateString();

    // Create a notification for today's action so the service counts it
    DB::table('organization_notifications')->insert([
        'id' => fake()->uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $actionToday->id,
        'notifiable_type' => ProcessAction::class,
        'notification_type' => 'judicial_action_detected',
        'is_viewed' => false,
        'is_notified' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/dashboard/summary?date_from={$today}&date_to={$today}");

    $response->assertStatus(200);
    $response->assertJson([
        'total_recent_actions' => 1, // Only today's action
        'alerts_red' => 1,
        'alerts_yellow' => 1,
        'alerts_green' => 0,
    ]);
});

it('does not filter semaphore counts by date', function (): void {
    // Create a Red process
    $processRed = Process::factory()->create();
    $processRed->organizations()->attach($this->organization->id, [
        'inactivity_alert_level' => 'red',
        'is_active' => true,
        'interest_date' => now(),
    ]);

    // Action of today
    ProcessAction::factory()->create(['process_id' => $processRed->id, 'action_date' => now()]);

    // Even if I filter for some future date where there are NO actions
    $futureDate = now()->addYear()->toDateString();
    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/dashboard/summary?date_from={$futureDate}&date_to={$futureDate}");

    $response->assertStatus(200);
    $response->assertJson([
        'total_recent_actions' => 0, // No actions in future
        'alerts_red' => 1,     // Red process still exists globally
    ]);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create([
        'name' => 'Toggle Org '.Str::uuid(),
    ]);
    $this->appUser = AppUser::factory()->create([
        'email' => 'toggle-status@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);

    $this->appUser->organizations()->attach($this->organization->id, [
        'is_owner' => true,
    ]);

    $this->process = Process::factory()->create();
    $this->process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
});

it('requires authentication to toggle process status', function (): void {
    $response = $this->patchJson("/api/app-user/processes/{$this->process->id}/status", [
        'is_active' => false,
    ]);

    $response->assertStatus(401);
});

it('validates that is_active field is required', function (): void {
    $response = $this->actingAs($this->appUser)
        ->patchJson("/api/app-user/processes/{$this->process->id}/status", []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['is_active']);
});

it('validates that is_active must be a boolean', function (): void {
    $response = $this->actingAs($this->appUser)
        ->patchJson("/api/app-user/processes/{$this->process->id}/status", [
            'is_active' => 'not-a-boolean',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['is_active']);
});

it('returns 404 when process does not exist', function (): void {
    $fakeId = 'non-existent-uuid-0000-000000000000';

    $response = $this->actingAs($this->appUser)
        ->patchJson("/api/app-user/processes/{$fakeId}/status", [
            'is_active' => false,
        ]);

    $response->assertStatus(404);
});

it('returns 404 when process belongs to a different organization', function (): void {
    $otherOrganization = Organization::factory()->create();
    $otherProcess = Process::factory()->create();
    $otherProcess->organizations()->attach($otherOrganization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->patchJson("/api/app-user/processes/{$otherProcess->id}/status", [
            'is_active' => false,
        ]);

    $response->assertStatus(404);
});

it('returns 422 when user has no organization', function (): void {
    $userWithoutOrg = AppUser::factory()->create([
        'email' => 'no-org-toggle@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($userWithoutOrg)
        ->patchJson("/api/app-user/processes/{$this->process->id}/status", [
            'is_active' => false,
        ]);

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('process.user_has_no_organization')],
    ]);
});

it('deactivates an active process successfully', function (): void {
    $response = $this->actingAs($this->appUser)
        ->patchJson("/api/app-user/processes/{$this->process->id}/status", [
            'is_active' => false,
        ]);

    $response->assertStatus(200);
    $response->assertJson([
        'message' => __('process.deactivated_successfully'),
    ]);

    $pivot = $this->process->organizations()
        ->where('organizations.id', $this->organization->id)
        ->first();

    expect((bool) $pivot->pivot->is_active)->toBeFalse();
});

it('activates an inactive process successfully', function (): void {
    $this->process->organizations()->updateExistingPivot($this->organization->id, [
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->appUser)
        ->patchJson("/api/app-user/processes/{$this->process->id}/status", [
            'is_active' => true,
        ]);

    $response->assertStatus(200);
    $response->assertJson([
        'message' => __('process.activated_successfully'),
    ]);

    $pivot = $this->process->organizations()
        ->where('organizations.id', $this->organization->id)
        ->first();

    expect((bool) $pivot->pivot->is_active)->toBeTrue();
});

it('keeps process active when setting is_active to true on an already active process', function (): void {
    $response = $this->actingAs($this->appUser)
        ->patchJson("/api/app-user/processes/{$this->process->id}/status", [
            'is_active' => true,
        ]);

    $response->assertStatus(200);
    $response->assertJson([
        'message' => __('process.activated_successfully'),
    ]);

    $pivot = $this->process->organizations()
        ->where('organizations.id', $this->organization->id)
        ->first();

    expect((bool) $pivot->pivot->is_active)->toBeTrue();
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Src\Application\Shared\Jobs\SyncProcessJob;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    JudicialSyncRun::query()->delete();
    Process::query()->delete();

    $this->user = User::factory()->create([
        'email' => 'admin@judicial-sync.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create();
});

it('requires authentication to trigger judicial sync', function (): void {
    $response = $this->postJson('/api/admin/judicial-sync', []);

    $response->assertStatus(401);
});

it('forbids non-admin users', function (): void {
    $plainUser = User::factory()->create([
        'email' => 'plain@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $response = $this->actingAs($plainUser)
        ->postJson('/api/admin/judicial-sync', []);

    $response->assertStatus(403);
});

it('validates radicado has 23 digits when provided', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/judicial-sync', [
            'radicado' => '123',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['radicado']);
});

it('returns no batch when there are no eligible radicados', function (): void {
    Bus::fake();

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/judicial-sync', []);

    $response->assertStatus(200);
    $response->assertJsonPath('jobs_dispatched', 0);
    $response->assertJsonPath('batch_dispatched', false);
    $response->assertJsonPath('radicado_filter', null);

    Bus::assertNothingBatched();
});

it('returns no batch when radicado filter does not match an active subscription', function (): void {
    Bus::fake();

    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $otherNumber = '11111111111111111111111';

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/judicial-sync', [
            'radicado' => $otherNumber,
        ]);

    $response->assertStatus(200);
    $response->assertJsonPath('jobs_dispatched', 0);
    $response->assertJsonPath('batch_dispatched', false);
    $response->assertJsonPath('radicado_filter', $otherNumber);

    Bus::assertNothingBatched();
});

it('dispatches a batch when at least one radicado has an active organization link', function (): void {
    Bus::fake();

    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/judicial-sync', []);

    $response->assertStatus(200);
    $response->assertJsonPath('jobs_dispatched', 1);
    $response->assertJsonPath('batch_dispatched', true);
    $response->assertJsonPath('radicado_filter', null);

    Bus::assertBatched(function ($batch) use ($process): bool {
        if ($batch->name !== 'Sync Judicial Processes Batch') {
            return false;
        }

        if (count($batch->jobs) !== 1) {
            return false;
        }

        $job = $batch->jobs[0];

        return $job instanceof SyncProcessJob
            && $job->processNumber === $process->process_number;
    });
});

it('dispatches a single job when radicado filter matches an active subscription', function (): void {
    Bus::fake();

    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/judicial-sync', [
            'radicado' => $process->process_number,
        ]);

    $response->assertStatus(200);
    $response->assertJsonPath('jobs_dispatched', 1);
    $response->assertJsonPath('batch_dispatched', true);
    $response->assertJsonPath('radicado_filter', $process->process_number);

    Bus::assertBatched(function ($batch) use ($process): bool {
        return $batch->name === 'Sync Judicial Processes Batch'
            && count($batch->jobs) === 1
            && $batch->jobs[0] instanceof SyncProcessJob
            && $batch->jobs[0]->processNumber === $process->process_number;
    });
});

it('ignores processes that only have inactive organization links for full sync', function (): void {
    Bus::fake();

    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/judicial-sync', []);

    $response->assertStatus(200);
    $response->assertJsonPath('jobs_dispatched', 0);
    $response->assertJsonPath('batch_dispatched', false);

    Bus::assertNothingBatched();
});

<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunDayMoment;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;
use Src\Domain\Process\Models\Process;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    JudicialSyncRun::query()->delete();
    Process::query()->delete();

    $this->user = User::factory()->create([
        'email' => 'admin@judicial-sync-history.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);
});

it('requires authentication to list judicial sync runs', function (): void {
    $response = $this->getJson('/api/admin/judicial-sync/runs');

    $response->assertStatus(401);
});

it('forbids non-admin users from listing judicial sync runs', function (): void {
    $plainUser = User::factory()->create([
        'email' => 'plain@judicial-sync-history.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $response = $this->actingAs($plainUser)->getJson('/api/admin/judicial-sync/runs');

    $response->assertStatus(403);
});

it('returns paginated judicial sync runs for admin', function (): void {
    JudicialSyncRun::factory()->count(2)->create([
        'status' => JudicialSyncRunStatus::NoProcesses,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/admin/judicial-sync/runs');

    $response->assertStatus(200);
    $response->assertJsonPath('data.0.status', JudicialSyncRunStatus::NoProcesses->value);
    $response->assertJsonPath(
        'data.0.status_label',
        __('enums.judicial_sync_run_status.'.JudicialSyncRunStatus::NoProcesses->value)
    );
    $response->assertJsonCount(2, 'data');
    $response->assertJsonPath('total', 2);
});

it('orders runs by creation time with most recent first', function (): void {
    $older = JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::NoProcesses,
        'started_at' => now()->subDay(),
    ]);
    $older->timestamps = false;
    $older->forceFill(['created_at' => now()->subHour()])->save();

    $newer = JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::NoProcesses,
        'started_at' => now()->subMinute(),
    ]);
    $newer->timestamps = false;
    $newer->forceFill(['created_at' => now()])->save();

    $response = $this->actingAs($this->user)->getJson('/api/admin/judicial-sync/runs');

    $response->assertStatus(200);
    expect($response->json('data.0.id'))->toBe($newer->id);
    expect($response->json('data.1.id'))->toBe($older->id);
});

it('exposes moment of day derived from started_at', function (): void {
    $run = JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::BatchCompleted,
        'started_at' => Carbon::parse('2026-06-01 09:30:00', config('app.timezone')),
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/admin/judicial-sync/runs');

    $response->assertStatus(200);
    $response->assertJsonPath('data.0.moment_of_day', JudicialSyncRunDayMoment::Morning->value);
    $response->assertJsonPath(
        'data.0.started_at',
        DateFormatHelper::formatDateTimeWithDayOfWeek($run->fresh()->started_at)
    );
});

it('filters by status', function (): void {
    JudicialSyncRun::factory()->create(['status' => JudicialSyncRunStatus::NoProcesses]);
    JudicialSyncRun::factory()->create(['status' => JudicialSyncRunStatus::BatchCompleted]);

    $response = $this->actingAs($this->user)->getJson(
        '/api/admin/judicial-sync/runs?status='.JudicialSyncRunStatus::BatchCompleted->value
    );

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.status', JudicialSyncRunStatus::BatchCompleted->value);
    $response->assertJsonPath(
        'data.0.status_label',
        __('enums.judicial_sync_run_status.'.JudicialSyncRunStatus::BatchCompleted->value)
    );
});

it('exposes data_source and data_source_label on each run', function (): void {
    JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::BatchCompleted,
        'data_source' => \Src\Domain\JudicialSync\Enums\JudicialSyncDataSource::Samai,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/admin/judicial-sync/runs');

    $response->assertStatus(200);
    $response->assertJsonPath('data.0.data_source', 'samai');
    $response->assertJsonPath('data.0.data_source_label', __('enums.judicial_sync_data_source.samai'));
});

it('filters by data_source', function (): void {
    JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::BatchCompleted,
        'data_source' => \Src\Domain\JudicialSync\Enums\JudicialSyncDataSource::JudicialBranch,
    ]);
    JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::BatchCompleted,
        'data_source' => \Src\Domain\JudicialSync\Enums\JudicialSyncDataSource::Samai,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/admin/judicial-sync/runs?data_source=samai');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.data_source', 'samai');
});

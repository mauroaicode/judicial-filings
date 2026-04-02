<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Src\Application\Shared\Jobs\CheckInactiveProcessesJob;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\Process;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create();
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);
});

it('sets inactivity_alert_level to red and creates notification for demandante process inactive > 90 days', function (): void {
    $process = Process::factory()->create([
        'last_activity_date' => Carbon::now()->subDays(91)->toDateString(),
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'lawyer_role' => ProcessLawyerRole::PLAINTIFF->value,
    ]);

    $job = new CheckInactiveProcessesJob;
    $job->handle();

    // Assert pivot was updated
    $pivot = $process->organizations()->first()->pivot;
    expect($pivot->inactivity_alert_level)->toBe('red');

    // Assert organization notification was created
    $notification = OrganizationNotification::query()
        ->where('organization_id', $this->organization->id)
        ->where('notifiable_id', $process->id)
        ->where('notification_type', 'inactividad_roja')
        ->first();

    expect($notification)->not->toBeNull();
});

it('sets inactivity_alert_level to green and creates green notification for demandado process inactive > 90 days', function (): void {
    $process = Process::factory()->create([
        'last_activity_date' => Carbon::now()->subDays(91)->toDateString(),
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'lawyer_role' => ProcessLawyerRole::DEFENDANT->value,
    ]);

    $job = new CheckInactiveProcessesJob;
    $job->handle();

    // Assert pivot was updated to green
    $pivot = $process->organizations()->first()->pivot;
    expect($pivot->inactivity_alert_level)->toBe('green');

    // Assert organization notification was created for green
    $notification = OrganizationNotification::query()
        ->where('organization_id', $this->organization->id)
        ->where('notifiable_id', $process->id)
        ->where('notification_type', 'inactividad_verde')
        ->first();

    expect($notification)->not->toBeNull();
});

it('sets inactivity_alert_level to yellow and creates yellow notification for demandante process inactive 45-89 days', function (): void {
    $process = Process::factory()->create([
        'last_activity_date' => Carbon::now()->subDays(50)->toDateString(),
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'lawyer_role' => ProcessLawyerRole::PLAINTIFF->value,
    ]);

    $job = new CheckInactiveProcessesJob;
    $job->handle();

    $pivot = $process->organizations()->first()->pivot;
    expect($pivot->inactivity_alert_level)->toBe('yellow');

    $notification = OrganizationNotification::query()
        ->where('organization_id', $this->organization->id)
        ->where('notifiable_id', $process->id)
        ->where('notification_type', 'inactividad_amarilla')
        ->first();

    expect($notification)->not->toBeNull();
});

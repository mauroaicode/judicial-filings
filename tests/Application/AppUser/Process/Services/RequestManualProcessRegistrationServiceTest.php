<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\DiscordAlerts\Jobs\SendToDiscordChannelJob;
use Src\Application\AppUser\Process\Services\RequestManualProcessRegistrationService;
use Src\Application\Shared\Notifications\ManualRegistrationRequestedNotification;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ManualRegistrationRequestReason;
use Src\Domain\Process\Enums\ManualRegistrationRequestStatus;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\ManualRegistrationRequest;
use Src\Domain\Process\Models\UnassignedProcessAction;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    Queue::fake();
    Notification::fake();
    ManualRegistrationRequest::query()->delete();
    UnassignedProcessAction::query()->delete();

    config([
        'discord-alerts.webhook_urls.manual_registration' => 'https://discord.com/api/webhooks/123456789/abcdefghijklmnopqrstuvwxyz',
    ]);

    $this->organization = Organization::factory()->create(['name' => 'Org Demo']);
    $this->appUser = AppUser::factory()->create([
        'name' => 'Ana',
        'last_name' => 'Pérez',
        'identification' => '1234567890',
        'email' => 'ana@example.com',
    ]);
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);
    $this->processNumber = '76892400300120260066300';

    $this->admin = User::factory()->create([
        'email' => 'admin-ops@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);
    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->admin->roles()->attach($adminRole->id);
});

it('creates a pending request, counts unassigned historial, and notifies discord once', function (): void {
    UnassignedProcessAction::query()->create([
        'process_number' => $this->processNumber,
        'action' => 'Auto de trámite',
        'annotation' => null,
        'registration_date' => '2026-01-10',
        'dedupe_hash' => UnassignedProcessAction::makeDedupeHash(
            $this->processNumber,
            'Auto de trámite',
            null,
            '2026-01-10',
        ),
    ]);
    UnassignedProcessAction::query()->create([
        'process_number' => $this->processNumber,
        'action' => 'Fijación en lista',
        'annotation' => null,
        'registration_date' => '2026-01-11',
        'dedupe_hash' => UnassignedProcessAction::makeDedupeHash(
            $this->processNumber,
            'Fijación en lista',
            null,
            '2026-01-11',
        ),
    ]);

    $request = app(RequestManualProcessRegistrationService::class)->handle(
        $this->processNumber,
        $this->organization->id,
        $this->appUser->id,
        ManualRegistrationRequestReason::NotFound,
        ProcessLawyerRole::PLAINTIFF,
    );

    expect($request->status)->toBe(ManualRegistrationRequestStatus::Pending)
        ->and($request->unassigned_actions_count)->toBe(2)
        ->and($request->discord_notified)->toBeTrue()
        ->and($request->reason)->toBe(ManualRegistrationRequestReason::NotFound);

    Queue::assertPushed(SendToDiscordChannelJob::class);
    Notification::assertSentTo($this->admin, ManualRegistrationRequestedNotification::class);
});

it('does not spam discord or admin notification when the same org+radicado is already pending', function (): void {
    $first = app(RequestManualProcessRegistrationService::class)->handle(
        $this->processNumber,
        $this->organization->id,
        $this->appUser->id,
        ManualRegistrationRequestReason::Private,
        ProcessLawyerRole::DEFENDANT,
    );

    Queue::assertPushed(SendToDiscordChannelJob::class, 1);
    Notification::assertSentToTimes($this->admin, ManualRegistrationRequestedNotification::class, 1);

    $second = app(RequestManualProcessRegistrationService::class)->handle(
        $this->processNumber,
        $this->organization->id,
        $this->appUser->id,
        ManualRegistrationRequestReason::Private,
        ProcessLawyerRole::DEFENDANT,
    );

    expect($second->id)->toBe($first->id)
        ->and(ManualRegistrationRequest::query()->count())->toBe(1);

    Queue::assertPushed(SendToDiscordChannelJob::class, 1);
    Notification::assertSentToTimes($this->admin, ManualRegistrationRequestedNotification::class, 1);
});

it('stores lawyer-provided subjects and process class on create', function (): void {
    $request = app(RequestManualProcessRegistrationService::class)->handle(
        $this->processNumber,
        $this->organization->id,
        $this->appUser->id,
        ManualRegistrationRequestReason::Private,
        ProcessLawyerRole::DEFENDANT,
        [
            'process_class' => 'Ordinario',
            'plaintiffs' => [['name' => 'A', 'identification' => null]],
            'defendants' => [['name' => 'B', 'identification' => '99']],
            'other_subjects' => [],
        ],
    );

    expect($request->process_class)->toBe('Ordinario')
        ->and($request->plaintiffs[0]['name'])->toBe('A')
        ->and($request->defendants[0]['identification'])->toBe('99');
});

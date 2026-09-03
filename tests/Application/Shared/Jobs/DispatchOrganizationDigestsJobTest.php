<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Src\Application\Shared\Jobs\DispatchOrganizationDigestsJob;
use Src\Application\Shared\Jobs\SendOrganizationDigestJob;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

it('dispatches digests only for active organizations with pending notifications', function (): void {
    Queue::fake();

    OrganizationNotification::query()->delete();

    $active = Organization::factory()->create(['is_active' => true]);
    $inactive = Organization::factory()->create(['is_active' => false]);

    foreach ([$active, $inactive] as $org) {
        $process = Process::factory()->create();
        $process->organizations()->attach($org->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
        ]);
        $action = ProcessAction::factory()->create(['process_id' => $process->id]);

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

    (new DispatchOrganizationDigestsJob)->handle();

    $pushedOrgIds = Queue::pushed(SendOrganizationDigestJob::class)
        ->map(fn (SendOrganizationDigestJob $job): string => $job->organization->id)
        ->all();

    expect($pushedOrgIds)
        ->toContain($active->id)
        ->not->toContain($inactive->id);
});

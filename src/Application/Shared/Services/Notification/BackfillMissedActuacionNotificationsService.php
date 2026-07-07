<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Services\Process\ProcessActionAlertNotificationService;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

readonly class BackfillMissedActuacionNotificationsResult
{
    /**
     * @param  list<string>  $actionsBackfilled
     * @param  list<string>  $actionsSkipped
     */
    public function __construct(
        public int $notificationsCreated,
        public bool $digestSent,
        public array $actionsBackfilled,
        public array $actionsSkipped,
    ) {}
}

readonly class BackfillMissedActuacionNotificationsService
{
    public function __construct(
        private ProcessActionAlertNotificationService $alertNotificationService,
        private NotificationDigestService $digestService,
    ) {}

    /**
     * Create missed actuación notifications and optionally send a targeted digest.
     *
     * @param  list<string>  $processNumbers
     */
    public function backfill(
        string $organizationId,
        array $processNumbers,
        ?string $discoveredOn = null,
        bool $sendDigest = true,
        bool $dryRun = false,
    ): BackfillMissedActuacionNotificationsResult {
        $organization = Organization::query()->findOrFail($organizationId);
        $processNumbers = array_values(array_filter(array_map(trim(...), $processNumbers)));

        $actionsBackfilled = [];
        $actionsSkipped = [];
        $notificationsCreated = 0;

        foreach ($processNumbers as $processNumber) {
            $process = Process::query()->where('process_number', $processNumber)->first();

            if ($process === null) {
                $actionsSkipped[] = "{$processNumber}:process_not_found";

                continue;
            }

            $isLinked = $organization->processes()
                ->where('processes.id', $process->id)
                ->wherePivot('is_active', true)
                ->exists();

            if (! $isLinked) {
                $actionsSkipped[] = "{$processNumber}:not_linked_to_org";

                continue;
            }

            $actionsQuery = ProcessAction::query()
                ->where('process_id', $process->id)->oldest();

            if ($discoveredOn !== null && $discoveredOn !== '') {
                $actionsQuery->whereDate('created_at', $discoveredOn);
            }

            $actions = $actionsQuery->get();

            if ($actions->isEmpty()) {
                $actionsSkipped[] = "{$processNumber}:no_matching_actions";

                continue;
            }

            foreach ($actions as $action) {
                if ($this->hasActuacionNotification($organizationId, $action)) {
                    $actionsSkipped[] = "{$processNumber}:{$action->id}:already_notified";

                    continue;
                }

                if ($dryRun) {
                    $actionsBackfilled[] = "{$processNumber}:{$action->id}";
                    $notificationsCreated++;

                    continue;
                }

                $this->alertNotificationService->handleForOrganization(
                    $action,
                    $process,
                    $organizationId,
                    'actuacion',
                    forceIncludeHistorical: true,
                );

                if ($this->hasActuacionNotification($organizationId, $action)) {
                    $actionsBackfilled[] = "{$processNumber}:{$action->id}";
                    $notificationsCreated++;
                } else {
                    $actionsSkipped[] = "{$processNumber}:{$action->id}:create_failed";
                }
            }
        }

        $digestSent = false;

        if ($sendDigest && ! $dryRun && $notificationsCreated > 0) {
            $this->digestService->sendDigest(
                $organization,
                $processNumbers,
                skipRegistrationCutoff: true,
            );
            $digestSent = true;

            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->info('BackfillMissedActuacionNotificationsService: digest dispatched', [
                    'organization_id' => $organizationId,
                    'process_numbers' => $processNumbers,
                    'notifications_created' => $notificationsCreated,
                ]);
        }

        return new BackfillMissedActuacionNotificationsResult(
            notificationsCreated: $notificationsCreated,
            digestSent: $digestSent,
            actionsBackfilled: $actionsBackfilled,
            actionsSkipped: $actionsSkipped,
        );
    }

    private function hasActuacionNotification(string $organizationId, ProcessAction $action): bool
    {
        return OrganizationNotification::query()
            ->where('organization_id', $organizationId)
            ->where('notifiable_id', $action->id)
            ->where('notifiable_type', $action->getMorphClass())
            ->where('notification_type', 'actuacion')
            ->exists();
    }
}

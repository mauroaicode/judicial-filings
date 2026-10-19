<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Collection;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessAction;

/**
 * Collects ProcessAction notifications that would be included in the next consolidado.
 *
 * Mirrors {@see NotificationDigestService::sendDigest} eligibility (active processes,
 * registration cutoff, already-sent suppression). Does not mark anything as notified.
 */
class CollectPendingDigestNotificationsService
{
    public function __construct(
        private readonly OrganizationNotificationRegistrationCutoffService $registrationCutoffService,
        private readonly AlreadySentDigestActuacionChecker $alreadySentDigestActuacionChecker,
    ) {}

    /**
     * Notifications that should appear in the next consolidado (already-sent duplicates removed).
     *
     * @param  list<string>|null  $limitToProcessNumbers
     * @return Collection<int, \Src\Domain\Notification\Models\OrganizationNotification>
     */
    public function handle(
        Organization $organization,
        ?array $limitToProcessNumbers = null,
        bool $skipRegistrationCutoff = false,
    ): Collection {
        [$keep] = $this->collectPartitioned($organization, $limitToProcessNumbers, $skipRegistrationCutoff);

        return $keep;
    }

    /**
     * @param  list<string>|null  $limitToProcessNumbers
     * @return array{
     *     0: Collection<int, \Src\Domain\Notification\Models\OrganizationNotification>,
     *     1: Collection<int, \Src\Domain\Notification\Models\OrganizationNotification>
     * }
     */
    public function collectPartitioned(
        Organization $organization,
        ?array $limitToProcessNumbers = null,
        bool $skipRegistrationCutoff = false,
    ): array {
        if (! $organization->canTrackProcesses()) {
            return [collect(), collect()];
        }

        $morphClass = (new ProcessAction)->getMorphClass();

        $lastNotifiedRegistrationDate = $skipRegistrationCutoff
            ? null
            : $this->registrationCutoffService->resolveLastNotifiedRegistrationDate($organization->id);

        $query = $organization->notifications()
            ->forActiveOrganizationProcesses($organization->id)
            ->where('notifiable_type', $morphClass)
            ->where(function ($q): void {
                $q->where('is_email_notified', false)
                    ->orWhere(function ($q2): void {
                        // Recover registration alerts that were marked notified without a digest
                        // (e.g. race during bulk import or a deleted digest row).
                        $q2->whereNull('notification_digest_id')
                            ->where('notification_type', 'actuacion_registro');
                    });
            })
            ->orderedByNotifiableRegistrationDateDesc()
            ->with(['notifiable.process']);

        if ($limitToProcessNumbers !== null && $limitToProcessNumbers !== []) {
            $query->forProcessNumbers($limitToProcessNumbers);
        }

        if ($lastNotifiedRegistrationDate !== null) {
            $query->forPendingDigestEligibleActuaciones(
                $lastNotifiedRegistrationDate,
                $this->registrationCutoffService->resolveDiscoveredTodayMinRegistrationDate(),
            );
        }

        $notifications = $query->get();

        if ($notifications->isEmpty()) {
            return [collect(), collect()];
        }

        return $this->alreadySentDigestActuacionChecker
            ->partitionPendingNotifications($organization->id, $notifications);
    }
}

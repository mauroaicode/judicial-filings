<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Facades\DB;
use Src\Domain\Notification\QueryBuilders\OrganizationNotificationQueryBuilder;
use Src\Domain\Process\Models\ProcessAction;

/**
 * Shared registration-date rules for actuación notifications.
 *
 * - Digest pending queue: uses the latest registration_date already included in a sent digest.
 * - App bell (list/count/create): uses a rolling window (new_instance_notify_days) so recent
 *   actuaciones stay visible while years-old history (2020, feb 2026, etc.) is suppressed.
 */
readonly class OrganizationNotificationRegistrationCutoffService
{
    /**
     * Latest process_actions.registration_date already included in a sent digest.
     */
    public function resolveLastNotifiedRegistrationDate(string $organizationId): ?string
    {
        $morphClass = (new ProcessAction)->getMorphClass();

        $maxDate = DB::table('organization_notifications')
            ->join('process_actions', 'organization_notifications.notifiable_id', '=', 'process_actions.id')
            ->where('organization_notifications.organization_id', $organizationId)
            ->where('organization_notifications.notifiable_type', $morphClass)
            ->where('organization_notifications.is_email_notified', true)
            ->whereNotNull('organization_notifications.notification_digest_id')
            // Never consider future-dated actuaciones: Rama Judicial occasionally publishes
            // actions with incorrect registration_date values in the future. If one of those
            // was ever included in a digest, it would push the cutoff into the future and block
            // all subsequent digests for the organization permanently.
            ->where('process_actions.registration_date', '<=', today()->format('Y-m-d'))
            ->max('process_actions.registration_date');

        if ($maxDate === null) {
            return null;
        }

        return \Illuminate\Support\Facades\Date::parse($maxDate)->format('Y-m-d');
    }

    /**
     * Rolling floor for in-app actuación notifications (bell list, badge count, new rows).
     */
    public function resolveAppNotificationRegistrationFloor(): string
    {
        $days = (int) config('judicial-sync.new_instance_notify_days', 7);

        if ($days <= 0) {
            return today()->format('Y-m-d');
        }

        return today()->subDays($days)->format('Y-m-d');
    }

    public function isEligibleForAppActuacionNotification(ProcessAction $action, string $organizationId): bool
    {
        if ($this->isNewlyDiscoveredActuacion($action)) {
            return true;
        }

        $floor = $this->resolveAppNotificationRegistrationFloor();

        return $action->registration_date->format('Y-m-d') >= $floor;
    }

    /**
     * Actuación first persisted during today's sync — notify even when Rama's
     * registration_date is older than the rolling window, unless it is older
     * than {@see config('judicial-sync.discovered_today_max_age_days')}.
     */
    public function isNewlyDiscoveredActuacion(ProcessAction $action): bool
    {
        if ($action->created_at->lt(today()->startOfDay())) {
            return false;
        }

        $minRegistration = $this->resolveDiscoveredTodayMinRegistrationDate();
        if ($minRegistration === null) {
            return false;
        }

        return $action->registration_date->format('Y-m-d') >= $minRegistration;
    }

    /**
     * Oldest registration_date allowed for the discovered-today bypass.
     * Null means the bypass is disabled.
     */
    public function resolveDiscoveredTodayMinRegistrationDate(): ?string
    {
        $days = max(0, (int) config('judicial-sync.discovered_today_max_age_days', 30));
        if ($days === 0) {
            return null;
        }

        return today()->subDays($days)->format('Y-m-d');
    }

    /**
     * @deprecated Use isEligibleForAppActuacionNotification() for app bell or applyDigestPendingCutoff() for digests.
     */
    public function isRegistrationDateWithinCutoff(ProcessAction $action, string $organizationId): bool
    {
        return $this->isEligibleForAppActuacionNotification($action, $organizationId);
    }

    /**
     * Digest pending queue only — same rule as NotificationDigestService.
     *
     * @param  OrganizationNotificationQueryBuilder  $query
     */
    public function applyDigestPendingCutoff(mixed $query, string $organizationId): mixed
    {
        $cutoff = $this->resolveLastNotifiedRegistrationDate($organizationId);

        if ($cutoff !== null) {
            $query->forPendingDigestEligibleActuaciones(
                $cutoff,
                $this->resolveDiscoveredTodayMinRegistrationDate(),
            );
        }

        return $query;
    }

    /**
     * App bell list and dashboard badge — rolling registration_date window.
     *
     * @param  OrganizationNotificationQueryBuilder  $query
     */
    public function applyBellDisplayFilter(mixed $query, string $organizationId): mixed
    {
        return $query->forProcessActionRegistrationDateOnOrAfter(
            $this->resolveAppNotificationRegistrationFloor(),
        );
    }
}

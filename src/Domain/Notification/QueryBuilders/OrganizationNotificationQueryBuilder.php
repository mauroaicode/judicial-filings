<?php

declare(strict_types=1);

namespace Src\Domain\Notification\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Process\Models\ProcessAction;

/**
 * @extends Builder<OrganizationNotification>
 */
class OrganizationNotificationQueryBuilder extends Builder
{
    public function whereOrganization(string $organizationId): self
    {
        return $this->where('organization_id', $organizationId);
    }

    public function whereNotificationType(string $type): self
    {
        return $this->where('notification_type', $type);
    }

    public function whereViewed(bool $viewed): self
    {
        return $this->where('is_viewed', $viewed);
    }

    public function whereUnviewed(): self
    {
        return $this->whereViewed(false);
    }

    public function withNotifiableAndProcess(): self
    {
        return $this->with(['notifiable' => fn ($q) => $q->with('process')]);
    }

    public function orderedByCreatedAt(): self
    {
        return $this->latest();
    }

    /**
     * Limit judicial-action notifications to processes currently linked to the organization.
     */
    public function forActiveOrganizationProcesses(string $organizationId): self
    {
        $morphClass = (new ProcessAction)->getMorphClass();

        return $this->where('organization_notifications.notifiable_type', $morphClass)
            ->whereIn('organization_notifications.notifiable_id', function ($sub) use ($organizationId): void {
                $sub->select('process_actions.id')
                    ->from('process_actions')
                    ->join('processes', 'processes.id', '=', 'process_actions.process_id')
                    ->join('organization_processes', 'organization_processes.process_id', '=', 'processes.id')
                    ->where('organization_processes.organization_id', $organizationId)
                    ->where('organization_processes.is_active', true)
                    ->whereNull('organization_processes.deleted_at');
            });
    }

    /**
     * Limit judicial-action notifications to specific radicado numbers.
     *
     * @param  array<int, string>  $processNumbers
     */
    public function forProcessNumbers(array $processNumbers): self
    {
        if ($processNumbers === []) {
            return $this;
        }

        $morphClass = (new ProcessAction)->getMorphClass();

        return $this->where('organization_notifications.notifiable_type', $morphClass)
            ->whereIn('organization_notifications.notifiable_id', function ($sub) use ($processNumbers): void {
                $sub->select('process_actions.id')
                    ->from('process_actions')
                    ->join('processes', 'processes.id', '=', 'process_actions.process_id')
                    ->whereIn('processes.process_number', $processNumbers);
            });
    }

    /**
     * Digest pending queue: sync cutoff, plus manual Excel imports (negative action_registration_id).
     */
    public function forPendingDigestEligibleActuaciones(
        string $registrationFloorDate,
        ?string $discoveredTodayMinRegistrationDate = null,
    ): self {
        $morphClass = (new ProcessAction)->getMorphClass();

        return $this->where(function (OrganizationNotificationQueryBuilder $query) use (
            $registrationFloorDate,
            $discoveredTodayMinRegistrationDate,
            $morphClass,
        ): void {
            $query->where(function (OrganizationNotificationQueryBuilder $standard) use (
                $registrationFloorDate,
                $discoveredTodayMinRegistrationDate,
            ): void {
                $standard->forActuacionVisibleByRegistrationOrDiscoveredToday(
                    $registrationFloorDate,
                    $discoveredTodayMinRegistrationDate,
                );
            })->orWhere(function (OrganizationNotificationQueryBuilder $manual) use ($morphClass): void {
                $manual->where('organization_notifications.notifiable_type', $morphClass)
                    ->whereIn('organization_notifications.notifiable_id', function ($sub): void {
                        $sub->select('process_actions.id')
                            ->from('process_actions')
                            ->where('action_registration_id', '<', 0);
                    });
            });
        });
    }

    /**
     * Actuaciones within the registration window, or first discovered today with a
     * registration_date not older than the discovered-today max age.
     */
    public function forActuacionVisibleByRegistrationOrDiscoveredToday(
        string $registrationFloorDate,
        ?string $discoveredTodayMinRegistrationDate = null,
    ): self {
        $morphClass = (new ProcessAction)->getMorphClass();
        $discoveredSince = today()->format('Y-m-d');

        return $this->where('organization_notifications.notifiable_type', $morphClass)
            ->whereIn('organization_notifications.notifiable_id', function ($sub) use (
                $registrationFloorDate,
                $discoveredSince,
                $discoveredTodayMinRegistrationDate,
            ): void {
                $sub->select('process_actions.id')
                    ->from('process_actions')
                    ->where(function (\Illuminate\Contracts\Database\Query\Builder $query) use (
                        $registrationFloorDate,
                        $discoveredSince,
                        $discoveredTodayMinRegistrationDate,
                    ): void {
                        $query->whereDate('registration_date', '>=', $registrationFloorDate);

                        if ($discoveredTodayMinRegistrationDate !== null) {
                            $query->orWhere(function (\Illuminate\Contracts\Database\Query\Builder $discovered) use (
                                $discoveredSince,
                                $discoveredTodayMinRegistrationDate,
                            ): void {
                                $discovered->whereDate('created_at', '>=', $discoveredSince)
                                    ->whereDate('registration_date', '>=', $discoveredTodayMinRegistrationDate);
                            });
                        }
                    });
            });
    }

    /**
     * Limit judicial-action notifications to those registered on or after a date.
     */
    public function forProcessActionRegistrationDateOnOrAfter(string $date): self
    {
        $morphClass = (new ProcessAction)->getMorphClass();

        return $this->where('organization_notifications.notifiable_type', $morphClass)
            ->whereIn('organization_notifications.notifiable_id', function ($sub) use ($date): void {
                $sub->select('process_actions.id')
                    ->from('process_actions')
                    ->whereDate('registration_date', '>=', $date);
            });
    }

    /**
     * Order by the related ProcessAction's registration_date (newest first).
     */
    public function orderedByNotifiableRegistrationDateDesc(): self
    {
        $morphClass = (new ProcessAction)->getMorphClass();

        return $this->join('process_actions', 'organization_notifications.notifiable_id', '=', 'process_actions.id')
            ->where('organization_notifications.notifiable_type', $morphClass)
            ->latest('process_actions.registration_date')
            ->orderByDesc('process_actions.cons_action')
            ->select('organization_notifications.*');
    }
}

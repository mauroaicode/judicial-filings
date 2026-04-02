<?php

declare(strict_types=1);

namespace Src\Domain\Notification\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Src\Application\AppUser\Notification\Data\NotificationDigestFilterData;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Process\Models\ProcessAction;

/**
 * @template TModel of NotificationDigest
 *
 * @extends Builder<TModel>
 */
class NotificationDigestQueryBuilder extends Builder
{
    /**
     * Filter by organization ID.
     */
    public function whereOrganization(string $organizationId): self
    {
        return $this->where('organization_id', $organizationId);
    }

    /**
     * Apply all filters from the data object.
     */
    public function filters(NotificationDigestFilterData $filters): self
    {
        $this->applyCreatedAtFilter($filters->created_at_from, $filters->created_at_to);
        $this->applyActionCriteriaFilters($filters);

        return $this->latest();
    }

    /**
     * Filter by reporting/digest date (created_at).
     */
    public function applyCreatedAtFilter(?string $from, ?string $to): self
    {
        if ($from && $to) {
            return $this->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        }

        if ($from) {
            return $this->where('created_at', '>=', $from.' 00:00:00');
        }

        if ($to) {
            return $this->where('created_at', '<=', $to.' 23:59:59');
        }

        return $this;
    }

    /**
     * Filter based on criteria belonging to the actions included in the digest.
     */
    public function applyActionCriteriaFilters(NotificationDigestFilterData $filters): self
    {
        if (! $filters->process_number &&
            ! $filters->registration_date_from && ! $filters->registration_date_to &&
            ! $filters->action_date_from && ! $filters->action_date_to &&
            ! $filters->term_start_date_from && ! $filters->term_start_date_to &&
            ! $filters->term_end_date_from && ! $filters->term_end_date_to &&
            ! $filters->alert_level && ! $filters->lawyer_role
        ) {
            return $this;
        }

        return $this->whereIn('id', function ($subQuery) use ($filters): void {
            $subQuery->select('notification_digest_id')
                ->from('organization_notifications')
                ->where('notifiable_type', ProcessAction::class)
                ->join('process_actions', 'process_actions.id', '=', 'organization_notifications.notifiable_id')
                ->join('processes', 'processes.id', '=', 'process_actions.process_id')
                ->join('organization_processes', function ($join) {
                    $join->on('organization_processes.process_id', '=', 'processes.id')
                        ->on('organization_processes.organization_id', '=', 'organization_notifications.organization_id');
                });

            if ($filters->process_number) {
                $subQuery->where('processes.process_number', 'LIKE', '%'.$filters->process_number.'%');
            }

            if ($filters->registration_date_from) {
                $subQuery->where('process_actions.registration_date', '>=', $filters->registration_date_from);
            }

            if ($filters->registration_date_to) {
                $subQuery->where('process_actions.registration_date', '<=', $filters->registration_date_to);
            }

            if ($filters->action_date_from) {
                $subQuery->where('process_actions.action_date', '>=', $filters->action_date_from);
            }

            if ($filters->action_date_to) {
                $subQuery->where('process_actions.action_date', '<=', $filters->action_date_to);
            }

            if ($filters->term_start_date_from) {
                $subQuery->where('process_actions.start_date', '>=', $filters->term_start_date_from);
            }

            if ($filters->term_start_date_to) {
                $subQuery->where('process_actions.start_date', '<=', $filters->term_start_date_to);
            }

            if ($filters->term_end_date_from) {
                $subQuery->where('process_actions.end_date', '>=', $filters->term_end_date_from);
            }

            if ($filters->term_end_date_to) {
                $subQuery->where('process_actions.end_date', '<=', $filters->term_end_date_to);
            }

            if ($filters->alert_level) {
                $subQuery->where('organization_processes.inactivity_alert_level', $filters->alert_level);
            }

            if ($filters->lawyer_role) {
                $subQuery->where('organization_processes.lawyer_role', $filters->lawyer_role);
            }
        });
    }
}

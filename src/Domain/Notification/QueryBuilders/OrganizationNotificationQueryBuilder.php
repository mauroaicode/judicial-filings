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
     * Order by the related ProcessAction's action_date (most recent first).
     * Use for notification types whose notifiable is ProcessAction (actuacion, actuacion_alerta).
     */
    public function orderedByNotifiableActionDate(): self
    {
        return $this->join('process_actions', 'organization_notifications.notifiable_id', '=', 'process_actions.id')
            ->where('organization_notifications.notifiable_type', ProcessAction::class)
            ->latest('process_actions.action_date')
            ->select('organization_notifications.*');
    }
}

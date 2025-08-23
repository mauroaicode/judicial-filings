<?php

namespace Core\Shared\Infrastructure\Traits;

use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotification;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Notifiable
{
    /**
     * Get all notifications for this model
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(OrganizationNotification::class, 'notifiable');
    }

    /**
     * Get notifications by type
     */
    public function notificationsByType(string $type): MorphMany
    {
        return $this->notifications()->byType($type);
    }

    /**
     * Check if organization has been notified for this model
     */
    public function hasOrganizationBeenNotified(string $organizationId, string $notificationType): bool
    {
        return $this->notifications()
            ->byOrganization($organizationId)
            ->byType($notificationType)
            ->where('is_notified', true)
            ->exists();
    }

    /**
     * Mark organization as notified for this model
     */
    public function markOrganizationAsNotified(string $organizationId, string $notificationType): void
    {
        $this->notifications()->updateOrCreate(
            [
                'organization_id' => $organizationId,
                'notification_type' => $notificationType,
            ],
            [
                'is_notified' => true,
                'notified_at' => now(),
            ]
        );
    }

    /**
     * Mark organization as viewed for this model
     */
    public function markOrganizationAsViewed(string $organizationId, string $notificationType): void
    {
        $this->notifications()->updateOrCreate(
            [
                'organization_id' => $organizationId,
                'notification_type' => $notificationType,
            ],
            [
                'is_viewed' => true,
                'viewed_at' => now(),
            ]
        );
    }

    /**
     * Get organizations that haven't been notified for this model
     */
    public function getOrganizationsNotNotified(string $notificationType, array $organizationIds): array
    {
        $notifiedOrganizationIds = $this->notifications()
            ->byType($notificationType)
            ->whereIn('organization_id', $organizationIds)
            ->where('is_notified', true)
            ->pluck('organization_id')
            ->toArray();

        return array_diff($organizationIds, $notifiedOrganizationIds);
    }

    /**
     * Get organizations that haven't viewed this notification
     */
    public function getOrganizationsNotViewed(string $notificationType, array $organizationIds): array
    {
        $viewedOrganizationIds = $this->notifications()
            ->byType($notificationType)
            ->whereIn('organization_id', $organizationIds)
            ->where('is_viewed', true)
            ->pluck('organization_id')
            ->toArray();

        return array_diff($organizationIds, $viewedOrganizationIds);
    }
}

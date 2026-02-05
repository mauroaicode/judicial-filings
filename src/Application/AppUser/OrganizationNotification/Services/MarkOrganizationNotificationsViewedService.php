<?php

declare(strict_types=1);

namespace Src\Application\AppUser\OrganizationNotification\Services;

use Src\Domain\Notification\Models\OrganizationNotification;

readonly class MarkOrganizationNotificationsViewedService
{
    /**
     * Mark notifications as viewed. Only notifications belonging to the organization are updated.
     *
     * @param  array<string>  $notificationIds
     * @return array{marked: int}
     */
    public function handle(string $organizationId, array $notificationIds): array
    {
        if ($notificationIds === []) {
            return ['marked' => 0];
        }

        $marked = $this->updateViewedForOrganization($organizationId, $notificationIds);

        return ['marked' => $marked];
    }

    private function updateViewedForOrganization(string $organizationId, array $notificationIds): int
    {
        return OrganizationNotification::query()
            ->whereIn('id', $notificationIds)
            ->whereOrganization($organizationId)
            ->whereUnviewed()
            ->update([
                'is_viewed' => true,
                'viewed_at' => now(),
            ]);
    }
}

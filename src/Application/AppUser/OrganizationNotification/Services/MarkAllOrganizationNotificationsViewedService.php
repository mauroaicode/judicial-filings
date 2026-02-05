<?php

declare(strict_types=1);

namespace Src\Application\AppUser\OrganizationNotification\Services;

use Src\Domain\Notification\Models\OrganizationNotification;

readonly class MarkAllOrganizationNotificationsViewedService
{
    /**
     * Mark all unviewed notifications of the organization as viewed.
     * Optionally filter by notification type (actuacion or actuacion_alerta).
     *
     * @return array{marked: int}
     */
    public function handle(string $organizationId, ?string $type = null): array
    {
        $query = OrganizationNotification::query()
            ->whereOrganization($organizationId)
            ->whereUnviewed();

        if ($type !== null && $type !== '') {
            $query->whereNotificationType($type);
        }

        $marked = $query->update([
            'is_viewed' => true,
            'viewed_at' => now(),
        ]);

        return ['marked' => $marked];
    }
}

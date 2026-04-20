<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Services;

use Src\Domain\Organization\Models\Organization;

class OrganizationNotificationStatusService
{
    /**
     * Update the status of all notification channels for the given organization.
     */
    public function handle(Organization $organization, bool $isActive): void
    {
        $organization->notificationChannels()->update(['is_active' => $isActive]);
    }
}

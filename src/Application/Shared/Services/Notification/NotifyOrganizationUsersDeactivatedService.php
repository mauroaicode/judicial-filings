<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Src\Application\Shared\Notifications\OrganizationDeactivatedNotification;
use Src\Domain\Organization\Enums\OrganizationStatusMode;
use Src\Domain\Organization\Models\Organization;

/**
 * Fan-out a friendly status email to every app user of the organization.
 */
readonly class NotifyOrganizationUsersDeactivatedService
{
    public function handle(Organization $organization, OrganizationStatusMode $mode): void
    {
        if ($mode === OrganizationStatusMode::Active) {
            return;
        }

        $organization->loadMissing('appUsers');

        foreach ($organization->appUsers as $appUser) {
            if ($appUser->email === '') {
                continue;
            }

            $appUser->notify(new OrganizationDeactivatedNotification($organization, $mode));
        }
    }
}

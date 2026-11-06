<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Config\Services;

use Src\Domain\Notification\Models\OrganizationNotificationChannel;

readonly class DeleteOrganizationNotificationChannelService
{
    public function handle(OrganizationNotificationChannel $channel): void
    {
        $channel->delete();
    }
}

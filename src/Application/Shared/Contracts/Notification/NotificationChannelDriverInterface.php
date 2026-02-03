<?php

declare(strict_types=1);

namespace Src\Application\Shared\Contracts\Notification;

use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

interface NotificationChannelDriverInterface
{
    /**
     * Send the notification through this channel.
     */
    public function send(OrganizationNotification $notification, OrganizationNotificationChannel $channel): void;
}

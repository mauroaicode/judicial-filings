<?php

declare(strict_types=1);

namespace Src\Application\Shared\Contracts\Notification;

use Src\Application\Shared\DTOs\TaskUrgencyAlert;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

interface TaskUrgencyChannelDriverInterface
{
    /**
     * Send the task urgency alert through this channel.
     */
    public function send(TaskUrgencyAlert $alert, OrganizationNotificationChannel $channel): void;
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Contracts\Notification;

use Src\Application\Shared\DTOs\TaskDueDateReminderAlert;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

interface TaskDueDateReminderChannelDriverInterface
{
    /**
     * Send the due-date reminder through this channel.
     */
    public function send(TaskDueDateReminderAlert $alert, OrganizationNotificationChannel $channel): void;
}

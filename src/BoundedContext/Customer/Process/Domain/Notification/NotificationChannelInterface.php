<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Domain\Notification;

use Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Data\NotificationData;

interface NotificationChannelInterface
{
    /**
     * Send notification through this channel
     */
    public function send(NotificationData $data): bool;

    /**
     * Get channel name
     */
    public function getChannelName(): string;
}

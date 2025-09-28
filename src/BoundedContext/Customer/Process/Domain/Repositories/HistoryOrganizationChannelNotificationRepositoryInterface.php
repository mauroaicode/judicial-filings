<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Domain\Repositories;

use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\HistoryOrganizationChannelNotification;
use Illuminate\Support\Collection;

interface HistoryOrganizationChannelNotificationRepositoryInterface
{
    /**
     * Create a new history record
     */
    public function create(array $data): HistoryOrganizationChannelNotification;

    /**
     * Update notification status
     */
    public function updateNotificationStatus(string $id, bool $isNotified, ?string $notifiedAt = null): bool;

    /**
     * Get failed notifications for a specific notifiable
     */
    public function getFailedNotifications(string $notifiableType, string $notifiableId): Collection;

    /**
     * Get failed notifications by organization channel
     */
    public function getFailedNotificationsByChannel(string $channelId): Collection;

    /**
     * Check if notification was sent successfully
     */
    public function wasNotificationSent(string $channelId, string $notifiableType, string $notifiableId): bool;

    /**
     * Get notification history for a specific notifiable
     */
    public function getNotificationHistory(string $notifiableType, string $notifiableId): Collection;

    /**
     * Get notification history by organization channel
     */
    public function getNotificationHistoryByChannel(string $channelId): Collection;
}

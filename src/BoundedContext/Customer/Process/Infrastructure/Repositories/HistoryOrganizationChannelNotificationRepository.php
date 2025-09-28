<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Infrastructure\Repositories;

use Core\BoundedContext\Customer\Process\Domain\Repositories\HistoryOrganizationChannelNotificationRepositoryInterface;
use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\HistoryOrganizationChannelNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HistoryOrganizationChannelNotificationRepository implements HistoryOrganizationChannelNotificationRepositoryInterface
{
    public function __construct(
        private HistoryOrganizationChannelNotification $model
    ) {}

    /**
     * Create a new history record
     */
    public function create(array $data): HistoryOrganizationChannelNotification
    {
        $data['id'] = $data['id'] ?? Str::uuid()->toString();
        
        return $this->model->create($data);
    }

    /**
     * Update notification status
     */
    public function updateNotificationStatus(string $id, bool $isNotified, ?string $notifiedAt = null): bool
    {
        $updateData = ['is_notified' => $isNotified];
        
        if ($isNotified && $notifiedAt) {
            $updateData['notified_at'] = $notifiedAt;
        }

        return $this->model::query()
            ->where('id', $id)
            ->update($updateData) > 0;
    }

    /**
     * Get failed notifications for a specific notifiable
     */
    public function getFailedNotifications(string $notifiableType, string $notifiableId): Collection
    {
        return $this->model::query()
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->where('is_notified', false)
            ->get();
    }

    /**
     * Get failed notifications by organization channel
     */
    public function getFailedNotificationsByChannel(string $channelId): Collection
    {
        return $this->model::query()
            ->where('organization_notification_channel_id', $channelId)
            ->where('is_notified', false)
            ->get();
    }

    /**
     * Check if notification was sent successfully
     */
    public function wasNotificationSent(string $channelId, string $notifiableType, string $notifiableId): bool
    {
        return $this->model::query()
            ->where('organization_notification_channel_id', $channelId)
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->where('is_notified', true)
            ->exists();
    }

    /**
     * Get notification history for a specific notifiable
     */
    public function getNotificationHistory(string $notifiableType, string $notifiableId): Collection
    {
        return $this->model::query()
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get notification history by organization channel
     */
    public function getNotificationHistoryByChannel(string $channelId): Collection
    {
        return $this->model::query()
            ->where('organization_notification_channel_id', $channelId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}

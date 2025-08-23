<?php

namespace Core\BoundedContext\Customer\Process\Infrastructure\Repositories;

use Core\BoundedContext\Customer\Process\Domain\Repositories\OrganizationNotificationRepositoryInterface;
use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotification;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process;
use Illuminate\Support\Collection;

class OrganizationNotificationRepository implements OrganizationNotificationRepositoryInterface
{
    public function hasOrganizationBeenNotified(string $organizationId, string $notifiableType, string $notifiableId, string $notificationType): bool
    {
        return OrganizationNotification::query()
            ->where('organization_id', $organizationId)
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->where('notification_type', $notificationType)
            ->where('is_notified', true)
            ->exists();
    }

    public function getOrganizationsNotNotified(string $notifiableType, string $notifiableId, string $notificationType, array $organizationIds): array
    {
        $notifiedOrganizationIds = OrganizationNotification::query()
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->where('notification_type', $notificationType)
            ->whereIn('organization_id', $organizationIds)
            ->where('is_notified', true)
            ->pluck('organization_id')
            ->toArray();

        return array_diff($organizationIds, $notifiedOrganizationIds);
    }

    public function markOrganizationAsNotified(string $organizationId, string $notifiableType, string $notifiableId, string $notificationType): void
    {
        OrganizationNotification::query()->updateOrCreate(
            [
                'organization_id' => $organizationId,
                'notifiable_type' => $notifiableType,
                'notifiable_id' => $notifiableId,
                'notification_type' => $notificationType,
            ],
            [
                'is_notified' => true,
                'notified_at' => now(),
            ]
        );
    }

    public function markOrganizationsAsNotified(string $notifiableType, string $notifiableId, string $notificationType, array $organizationIds): void
    {
        foreach ($organizationIds as $organizationId) {
            $this->markOrganizationAsNotified($organizationId, $notifiableType, $notifiableId, $notificationType);
        }
    }

    public function markOrganizationAsViewed(string $organizationId, string $notifiableType, string $notifiableId, string $notificationType): void
    {
        OrganizationNotification::query()->updateOrCreate(
            [
                'organization_id' => $organizationId,
                'notifiable_type' => $notifiableType,
                'notifiable_id' => $notifiableId,
                'notification_type' => $notificationType,
            ],
            [
                'is_viewed' => true,
                'viewed_at' => now(),
            ]
        );
    }

    public function getOrganizationsNotViewed(string $notifiableType, string $notifiableId, string $notificationType, array $organizationIds): array
    {
        $viewedOrganizationIds = OrganizationNotification::query()
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->where('notification_type', $notificationType)
            ->whereIn('organization_id', $organizationIds)
            ->where('is_viewed', true)
            ->pluck('organization_id')
            ->toArray();

        return array_diff($organizationIds, $viewedOrganizationIds);
    }

    public function getNotificationHistory(string $notifiableType, string $notifiableId, string $notificationType): Collection
    {
        return OrganizationNotification::query()
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->where('notification_type', $notificationType)
            ->with('organization')
            ->get();
    }

    public function getAllNotificationRecords(): Collection
    {
        return OrganizationNotification::query()
            ->with(['organization', 'notifiable'])
            ->get();
    }

    public function cleanFailedNotifications(string $notifiableType, string $notifiableId, string $notificationType): void
    {
        OrganizationNotification::query()
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->where('notification_type', $notificationType)
            ->where('is_notified', false)
            ->delete();
    }

    public function hasAlreadyNotifiedMultipleInstances(string $processNumber, string $notificationType): bool
    {
        return Process::query()
            ->where('process_number', $processNumber)
            ->whereHas('notifications', function ($query) use ($notificationType) {
                $query->where('notification_type', $notificationType)
                      ->where('is_notified', true);
            })
            ->exists();
    }

    public function getOrganizationsNotNotifiedByProcessNumber(string $processNumber, string $notificationType, array $organizationIds): array
    {
        $notifiedOrganizationIds = Process::query()
            ->where('process_number', $processNumber)
            ->whereHas('notifications', function ($query) use ($notificationType) {
                $query->where('notification_type', $notificationType)
                      ->where('is_notified', true);
            })
            ->with('notifications')
            ->get()
            ->flatMap(function ($process) use ($notificationType) {
                return $process->notifications
                    ->where('notification_type', $notificationType)
                    ->where('is_notified', true)
                    ->pluck('organization_id');
            })
            ->unique()
            ->toArray();

        return array_diff($organizationIds, $notifiedOrganizationIds);
    }

    public function markOrganizationsAsNotifiedByProcessNumber(string $processNumber, string $notificationType, array $organizationIds): void
    {
        $processes = Process::query()
            ->where('process_number', $processNumber)
            ->get();

        foreach ($processes as $process) {
            foreach ($organizationIds as $organizationId) {
                $this->markOrganizationAsNotified(
                    $organizationId,
                    Process::class,
                    $process->id,
                    $notificationType
                );
            }
        }
    }
}

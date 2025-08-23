<?php

namespace Core\BoundedContext\Customer\Process\Domain\Repositories;

use Illuminate\Support\Collection;

interface OrganizationNotificationRepositoryInterface
{
    /**
     * Check if organization has been notified for a specific notifiable
     */
    public function hasOrganizationBeenNotified(string $organizationId, string $notifiableType, string $notifiableId, string $notificationType): bool;

    /**
     * Get organizations that haven't been notified for a specific notifiable
     */
    public function getOrganizationsNotNotified(string $notifiableType, string $notifiableId, string $notificationType, array $organizationIds): array;

    /**
     * Mark organization as notified for a specific notifiable
     */
    public function markOrganizationAsNotified(string $organizationId, string $notifiableType, string $notifiableId, string $notificationType): void;

    /**
     * Mark multiple organizations as notified for a specific notifiable
     */
    public function markOrganizationsAsNotified(string $notifiableType, string $notifiableId, string $notificationType, array $organizationIds): void;

    /**
     * Mark organization as viewed for a specific notifiable
     */
    public function markOrganizationAsViewed(string $organizationId, string $notifiableType, string $notifiableId, string $notificationType): void;

    /**
     * Get organizations that haven't viewed a specific notification
     */
    public function getOrganizationsNotViewed(string $notifiableType, string $notifiableId, string $notificationType, array $organizationIds): array;

    /**
     * Get notification history for a specific notifiable
     */
    public function getNotificationHistory(string $notifiableType, string $notifiableId, string $notificationType): Collection;

    /**
     * Get all notification records
     */
    public function getAllNotificationRecords(): Collection;

    /**
     * Clean failed notifications for a specific notifiable
     */
    public function cleanFailedNotifications(string $notifiableType, string $notifiableId, string $notificationType): void;

    /**
     * Check if multiple instances notification has already been sent for a process number
     */
    public function hasAlreadyNotifiedMultipleInstances(string $processNumber, string $notificationType): bool;

    /**
     * Get organizations not notified by process number
     */
    public function getOrganizationsNotNotifiedByProcessNumber(string $processNumber, string $notificationType, array $organizationIds): array;

    /**
     * Mark organizations as notified by process number
     */
    public function markOrganizationsAsNotifiedByProcessNumber(string $processNumber, string $notificationType, array $organizationIds): void;
}

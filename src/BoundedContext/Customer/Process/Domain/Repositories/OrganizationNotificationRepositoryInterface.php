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

    /**
     * Get organizations not notified by process ID
     */
    public function getOrganizationsNotNotifiedByProcessId(string $processId, string $notificationType, array $organizationIds): array;

    /**
     * Create notification records for multiple organizations with is_notified = false
     * This allows for retry mechanisms in case of failures
     */
    public function createNotificationRecordsForOrganizations(
        string $notifiableId,
        string $notifiableType,
        string $notificationType,
        array $organizationIds
    ): void;

    /**
     * Create notification records for organizations interested in a specific process number
     */
    public function createNotificationRecordsForProcessNumber(
        string $processNumber,
        string $notificationType,
        array $organizationIds
    ): void;

    /**
     * Get existing process IDs for a given process number
     */
    public function getExistingProcessIds(string $processNumber): array;

    /**
     * Get organizations that haven't been notified about multiple instances for a specific process number
     */
    public function getOrganizationsNotNotifiedMultipleInstances(string $processNumber, string $notificationType, array $organizationIds): array;
}

<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Domain\Repositories;

use Core\Shared\Domain\Enums\NotificationChannelType;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Organization;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for Organization Notification Channel Repository
 */
interface OrganizationNotificationChannelRepositoryInterface
{
    /**
     * Get active notification channels for an organization by type.
     */
    public function getActiveChannelsByType(Organization $organization, NotificationChannelType $type): Collection;

    /**
     * Get all active notification channels for an organization.
     */
    public function getAllActiveChannels(Organization $organization): Collection;

    /**
     * Check if an organization has active channels of a specific type.
     */
    public function hasActiveChannels(Organization $organization, NotificationChannelType $type): bool;

    /**
     * Get the primary (priority 1) active channel for a specific type.
     */
    public function getPrimaryChannel(Organization $organization, NotificationChannelType $type): ?\Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotificationChannel;

    /**
     * Get all active channels for a specific type ordered by priority.
     */
    public function getChannelsByPriority(Organization $organization, NotificationChannelType $type): Collection;

    /**
     * Count active channels for a specific type.
     */
    public function countActiveChannels(Organization $organization, NotificationChannelType $type): int;

    /**
     * Get channels that can receive notifications (active and valid).
     */
    public function getNotificationReadyChannels(Organization $organization, NotificationChannelType $type): Collection;

    /**
     * Get all active channels for multiple organizations by type.
     */
    public function getActiveChannelsForOrganizations(array $organizationIds, NotificationChannelType $type): Collection;

    /**
     * Get all active channels for multiple organizations grouped by organization.
     */
    public function getActiveChannelsForOrganizationsGrouped(array $organizationIds, NotificationChannelType $type): \Illuminate\Support\Collection;

    /**
     * Find notification channel by ID.
     */
    public function findById(string $id): ?\Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotificationChannel;

    /**
     * Create a new notification channel.
     */
    public function create(array $data): \Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotificationChannel;

    /**
     * Update an existing notification channel.
     */
    public function update(string $id, array $data): bool;

    /**
     * Delete a notification channel.
     */
    public function delete(string $id): bool;

    /**
     * Activate a notification channel.
     */
    public function activate(string $id): bool;

    /**
     * Deactivate a notification channel.
     */
    public function deactivate(string $id): bool;
}

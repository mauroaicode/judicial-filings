<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Services;

use Core\BoundedContext\Customer\Process\Domain\Repositories\OrganizationNotificationChannelRepositoryInterface;
use Core\Shared\Domain\Enums\NotificationChannelType;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Organization;

/**
 * Service for managing organization notification channels.
 * This service acts as a facade over the repository layer.
 */
class NotificationChannelService
{
    public function __construct(
        private readonly OrganizationNotificationChannelRepositoryInterface $repository
    ) {}

    /**
     * Get active notification channels for an organization by type.
     */
    public function getActiveChannelsByType(Organization $organization, NotificationChannelType $type): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->getActiveChannelsByType($organization, $type);
    }

    /**
     * Get all active notification channels for an organization.
     */
    public function getAllActiveChannels(Organization $organization): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->getAllActiveChannels($organization);
    }

    /**
     * Check if an organization has active channels of a specific type.
     */
    public function hasActiveChannels(Organization $organization, NotificationChannelType $type): bool
    {
        return $this->repository->hasActiveChannels($organization, $type);
    }

    /**
     * Get the primary (priority 1) active channel for a specific type.
     */
    public function getPrimaryChannel(Organization $organization, NotificationChannelType $type): ?\Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotificationChannel
    {
        return $this->repository->getPrimaryChannel($organization, $type);
    }

    /**
     * Get all active channels for a specific type ordered by priority.
     */
    public function getChannelsByPriority(Organization $organization, NotificationChannelType $type): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->getChannelsByPriority($organization, $type);
    }

    /**
     * Count active channels for a specific type.
     */
    public function countActiveChannels(Organization $organization, NotificationChannelType $type): int
    {
        return $this->repository->countActiveChannels($organization, $type);
    }

    /**
     * Get channels that can receive notifications (active and valid).
     */
    public function getNotificationReadyChannels(Organization $organization, NotificationChannelType $type): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->getNotificationReadyChannels($organization, $type);
    }

    /**
     * Get all active channels for multiple organizations by type.
     */
    public function getActiveChannelsForOrganizations(array $organizationIds, NotificationChannelType $type): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->getActiveChannelsForOrganizations($organizationIds, $type);
    }

    /**
     * Get all active channels for multiple organizations grouped by organization.
     */
    public function getActiveChannelsForOrganizationsGrouped(array $organizationIds, NotificationChannelType $type): \Illuminate\Support\Collection
    {
        return $this->repository->getActiveChannelsForOrganizationsGrouped($organizationIds, $type);
    }

    /**
     * Get channels by organization with custom query filters.
     */
    public function getChannelsByOrganizationWithCustomQuery(Organization $organization, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->getChannelsByOrganizationWithCustomQuery($organization, $filters);
    }

    /**
     * Get channels statistics for an organization.
     */
    public function getChannelsStatistics(Organization $organization): array
    {
        return $this->repository->getChannelsStatistics($organization);
    }

    /**
     * Bulk update channels priority for an organization.
     */
    public function bulkUpdatePriority(Organization $organization, NotificationChannelType $type, array $priorityMap): bool
    {
        return $this->repository->bulkUpdatePriority($organization, $type, $priorityMap);
    }

    /**
     * Get channels that need attention (inactive or empty values).
     */
    public function getChannelsNeedingAttention(Organization $organization): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->getChannelsNeedingAttention($organization);
    }
}

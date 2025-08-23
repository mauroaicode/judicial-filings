<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Infrastructure\Repositories;

use Core\BoundedContext\Customer\Process\Domain\Repositories\OrganizationNotificationChannelRepositoryInterface;
use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotificationChannel;
use Core\Shared\Domain\Enums\NotificationChannelType;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Organization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Concrete implementation of Organization Notification Channel Repository
 */
class OrganizationNotificationChannelRepository implements OrganizationNotificationChannelRepositoryInterface
{
    public function __construct(
        private readonly OrganizationNotificationChannel $model
    ) {}

    /**
     * Get active notification channels for an organization by type.
     */
    public function getActiveChannelsByType(Organization $organization, NotificationChannelType $type): Collection
    {
        return $this->model::query()
            ->where('organization_id', $organization->id)
            ->where('channel_type', $type)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();
    }

    /**
     * Get all active notification channels for an organization.
     */
    public function getAllActiveChannels(Organization $organization): Collection
    {
        return $this->model::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('channel_type')
            ->orderBy('priority')
            ->get();
    }

    /**
     * Check if an organization has active channels of a specific type.
     */
    public function hasActiveChannels(Organization $organization, NotificationChannelType $type): bool
    {
        return $this->model::query()
            ->where('organization_id', $organization->id)
            ->where('channel_type', $type)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get the primary (priority 1) active channel for a specific type.
     */
    public function getPrimaryChannel(Organization $organization, NotificationChannelType $type): ?OrganizationNotificationChannel
    {
        return $this->model::query()
            ->where('organization_id', $organization->id)
            ->where('channel_type', $type)
            ->where('is_active', true)
            ->where('priority', 1)
            ->first();
    }

    /**
     * Get all active channels for a specific type ordered by priority.
     */
    public function getChannelsByPriority(Organization $organization, NotificationChannelType $type): Collection
    {
        return $this->model::query()
            ->where('organization_id', $organization->id)
            ->where('channel_type', $type)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();
    }

    /**
     * Count active channels for a specific type.
     */
    public function countActiveChannels(Organization $organization, NotificationChannelType $type): int
    {
        return $this->model::query()
            ->where('organization_id', $organization->id)
            ->where('channel_type', $type)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Get channels that can receive notifications (active and valid).
     */
    public function getNotificationReadyChannels(Organization $organization, NotificationChannelType $type): Collection
    {
        return $this->model::query()
            ->where('organization_id', $organization->id)
            ->where('channel_type', $type)
            ->where('is_active', true)
            ->whereNotNull('channel_value')
            ->where('channel_value', '!=', '')
            ->orderBy('priority')
            ->get();
    }

    /**
     * Get all active channels for multiple organizations by type.
     */
    public function getActiveChannelsForOrganizations(array $organizationIds, NotificationChannelType $type): Collection
    {
        return $this->model::query()
            ->whereIn('organization_id', $organizationIds)
            ->where('channel_type', $type)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();
    }

    /**
     * Get all active channels for multiple organizations grouped by organization.
     */
    public function getActiveChannelsForOrganizationsGrouped(array $organizationIds, NotificationChannelType $type): \Illuminate\Support\Collection
    {
        return $this->model::query()
            ->whereIn('organization_id', $organizationIds)
            ->where('channel_type', $type)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get()
            ->groupBy('organization_id');
    }

    /**
     * Find notification channel by ID.
     */
    public function findById(string $id): ?OrganizationNotificationChannel
    {
        return $this->model::query()->find($id);
    }

    /**
     * Create a new notification channel.
     */
    public function create(array $data): OrganizationNotificationChannel
    {
        return $this->model::create($data);
    }

    /**
     * Update an existing notification channel.
     */
    public function update(string $id, array $data): bool
    {
        $channel = $this->findById($id);

        if (!$channel) {
            return false;
        }

        return $channel->update($data);
    }

    /**
     * Delete a notification channel.
     */
    public function delete(string $id): bool
    {
        $channel = $this->findById($id);

        if (!$channel) {
            return false;
        }

        return $channel->delete();
    }

    /**
     * Activate a notification channel.
     */
    public function activate(string $id): bool
    {
        return $this->update($id, ['is_active' => true]);
    }

    /**
     * Deactivate a notification channel.
     */
    public function deactivate(string $id): bool
    {
        return $this->update($id, ['is_active' => false]);
    }

    /**
     * Get channels by organization with custom query builder for complex queries.
     */
    public function getChannelsByOrganizationWithCustomQuery(Organization $organization, array $filters = []): Collection
    {
        $query = $this->model::query()
            ->where('organization_id', $organization->id);

        // Aplicar filtros dinámicos
        if (isset($filters['channel_type'])) {
            $query->where('channel_type', $filters['channel_type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['search'])) {
            $query->where('channel_value', 'like', '%' . $filters['search'] . '%');
        }

        // Ordenamiento
        $orderBy = $filters['order_by'] ?? 'priority';
        $direction = $filters['direction'] ?? 'asc';
        $query->orderBy($orderBy, $direction);

        return $query->get();
    }

    /**
     * Get channels statistics for an organization.
     */
    public function getChannelsStatistics(Organization $organization): array
    {
        $stats = $this->model::query()
            ->select('channel_type', 'is_active', DB::raw('count(*) as count'))
            ->where('organization_id', $organization->id)
            ->groupBy('channel_type', 'is_active')
            ->get()
            ->groupBy('channel_type');

        $result = [];
        foreach (NotificationChannelType::cases() as $channelType) {
            $activeCount = $stats->get($channelType->value)?->where('is_active', true)->first()?->count ?? 0;
            $inactiveCount = $stats->get($channelType->value)?->where('is_active', false)->first()?->count ?? 0;

            $result[$channelType->value] = [
                'active' => $activeCount,
                'inactive' => $inactiveCount,
                'total' => $activeCount + $inactiveCount,
            ];
        }

        return $result;
    }

    /**
     * Bulk update channels priority for an organization.
     */
    public function bulkUpdatePriority(Organization $organization, NotificationChannelType $type, array $priorityMap): bool
    {
        return DB::transaction(function () use ($organization, $type, $priorityMap) {
            foreach ($priorityMap as $channelId => $newPriority) {
                $this->update($channelId, ['priority' => $newPriority]);
            }
            return true;
        });
    }

    /**
     * Get channels that need attention (inactive or empty values).
     */
    public function getChannelsNeedingAttention(Organization $organization): Collection
    {
        return $this->model::query()
            ->where('organization_id', $organization->id)
            ->where(function ($query) {
                $query->where('is_active', false)
                      ->orWhereNull('channel_value')
                      ->orWhere('channel_value', '');
            })
            ->orderBy('channel_type')
            ->orderBy('priority')
            ->get();
    }
}

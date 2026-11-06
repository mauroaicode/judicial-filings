<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Validation\ValidationException;
use Src\Domain\Notification\Enums\NotificationChannelType;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
use Src\Domain\Notification\Models\OrganizationNotificationChannelLimit;

/**
 * Resolves how many destinations an organization may keep per channel.
 *
 *  1. organization_notification_channel_limits.max_channels when a row exists
 *  2. Otherwise config('organization.defaults.notification_channel_limit')
 */
readonly class OrganizationNotificationChannelQuotaService
{
    /**
     * @return list<string>
     */
    public function configurableTypes(): array
    {
        return NotificationChannelType::configurable();
    }

    public function defaultLimit(): int
    {
        $default = config('organization.defaults.notification_channel_limit', 3);

        if ($default === null || $default === '') {
            return 3;
        }

        return max(0, (int) $default);
    }

    public function configuredLimit(string $organizationId, string $channelType): ?int
    {
        $limit = OrganizationNotificationChannelLimit::query()
            ->where('organization_id', $organizationId)
            ->where('channel_type', $channelType)
            ->value('max_channels');

        if ($limit === null) {
            return null;
        }

        return max(0, (int) $limit);
    }

    public function resolveLimit(string $organizationId, string $channelType): int
    {
        return $this->configuredLimit($organizationId, $channelType) ?? $this->defaultLimit();
    }

    public function used(string $organizationId, string $channelType): int
    {
        return OrganizationNotificationChannel::query()
            ->where('organization_id', $organizationId)
            ->where('channel_type', $channelType)
            ->count();
    }

    public function assertCanAdd(string $organizationId, string $channelType): void
    {
        $limit = $this->resolveLimit($organizationId, $channelType);
        $used = $this->used($organizationId, $channelType);

        if ($used >= $limit) {
            throw ValidationException::withMessages([
                'channel_type' => __('organization.notification_channel_limit_reached', [
                    'limit' => $limit,
                    'channel' => trans_choice('organization.notification_channel_types.'.$channelType, $limit),
                ]),
            ]);
        }
    }

    /**
     * @return list<array{
     *     channel_type: string,
     *     limit: int,
     *     configured_limit: int|null,
     *     default_limit: int,
     *     used: int,
     *     remaining: int,
     *     can_add: bool
     * }>
     */
    public function summarize(string $organizationId): array
    {
        $default = $this->defaultLimit();
        $rows = [];

        foreach ($this->configurableTypes() as $channelType) {
            $configured = $this->configuredLimit($organizationId, $channelType);
            $limit = $configured ?? $default;
            $used = $this->used($organizationId, $channelType);

            $rows[] = [
                'channel_type' => $channelType,
                'limit' => $limit,
                'configured_limit' => $configured,
                'default_limit' => $default,
                'used' => $used,
                'remaining' => max(0, $limit - $used),
                'can_add' => $used < $limit,
            ];
        }

        return $rows;
    }
}

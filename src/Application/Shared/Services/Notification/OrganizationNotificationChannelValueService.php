<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Validation\ValidationException;
use Src\Domain\Notification\Enums\NotificationChannelType;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

/**
 * Normalizes and checks a destination before it is stored on a channel.
 */
readonly class OrganizationNotificationChannelValueService
{
    public function prepare(string $channelType, string $channelValue): string
    {
        if (! in_array($channelType, NotificationChannelType::configurable(), true)) {
            throw ValidationException::withMessages([
                'channel_type' => __('organization.notification_channel_invalid_type'),
            ]);
        }

        $value = trim($channelValue);

        if ($channelType === NotificationChannelType::Email->value) {
            $value = strtolower($value);

            if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'channel_value' => __('organization.notification_channel_invalid_email'),
                ]);
            }

            return $value;
        }

        $phone = preg_replace('/[\s\-()]/', '', $value) ?? '';

        if (! preg_match('/^\+?\d{8,15}$/', $phone)) {
            throw ValidationException::withMessages([
                'channel_value' => __('organization.notification_channel_invalid_phone'),
            ]);
        }

        return $phone;
    }

    public function assertAvailable(
        string $organizationId,
        string $channelType,
        string $channelValue,
        ?string $ignoreId = null,
    ): void {
        $query = OrganizationNotificationChannel::query()
            ->where('organization_id', $organizationId)
            ->where('channel_type', $channelType)
            ->where('channel_value', $channelValue);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'channel_value' => __('organization.notification_channel_duplicate'),
            ]);
        }
    }

    public function nextPriority(string $organizationId, string $channelType): int
    {
        $current = OrganizationNotificationChannel::query()
            ->where('organization_id', $organizationId)
            ->where('channel_type', $channelType)
            ->max('priority');

        return ((int) $current) + 1;
    }
}

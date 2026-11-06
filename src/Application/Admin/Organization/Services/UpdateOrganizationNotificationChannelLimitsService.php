<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Services;

use Spatie\LaravelData\Optional;
use Src\Application\Admin\Organization\Data\UpdateOrganizationNotificationChannelLimitsData;
use Src\Domain\Notification\Enums\NotificationChannelType;
use Src\Domain\Notification\Models\OrganizationNotificationChannelLimit;
use Src\Domain\Organization\Models\Organization;

readonly class UpdateOrganizationNotificationChannelLimitsService
{
    public function handle(Organization $organization, UpdateOrganizationNotificationChannelLimitsData $data): void
    {
        foreach (NotificationChannelType::configurable() as $channelType) {
            $value = $data->{$channelType};

            if ($value instanceof Optional) {
                continue;
            }

            if ($value === null) {
                OrganizationNotificationChannelLimit::query()
                    ->where('organization_id', $organization->id)
                    ->where('channel_type', $channelType)
                    ->delete();

                continue;
            }

            OrganizationNotificationChannelLimit::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'channel_type' => $channelType,
                ],
                [
                    'max_channels' => $value,
                ],
            );
        }
    }
}

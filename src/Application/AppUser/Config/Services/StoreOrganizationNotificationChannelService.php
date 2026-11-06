<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Config\Services;

use Src\Application\AppUser\Config\Data\StoreOrganizationNotificationChannelData;
use Src\Application\Shared\Services\Notification\OrganizationNotificationChannelEmailVerificationService;
use Src\Application\Shared\Services\Notification\OrganizationNotificationChannelQuotaService;
use Src\Application\Shared\Services\Notification\OrganizationNotificationChannelValueService;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

readonly class StoreOrganizationNotificationChannelService
{
    public function __construct(
        private OrganizationNotificationChannelQuotaService $quotaService,
        private OrganizationNotificationChannelValueService $valueService,
        private OrganizationNotificationChannelEmailVerificationService $emailVerificationService,
    ) {}

    public function handle(
        StoreOrganizationNotificationChannelData $data,
        string $organizationId,
    ): OrganizationNotificationChannel {
        $value = $this->valueService->prepare($data->channel_type, $data->channel_value);

        $this->quotaService->assertCanAdd($organizationId, $data->channel_type);
        $this->valueService->assertAvailable($organizationId, $data->channel_type, $value);

        /** @var OrganizationNotificationChannel $channel */
        $channel = OrganizationNotificationChannel::query()->create([
            'organization_id' => $organizationId,
            'channel_type' => $data->channel_type,
            'channel_value' => $value,
            'is_active' => $data->is_active,
            'validated_at' => $this->emailVerificationService->validatedAtFor($data->channel_type, $value),
            'priority' => $this->valueService->nextPriority($organizationId, $data->channel_type),
        ]);

        $this->emailVerificationService->sendIfPending($channel);

        return $channel;
    }
}

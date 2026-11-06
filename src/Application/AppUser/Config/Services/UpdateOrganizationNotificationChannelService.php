<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Config\Services;

use Spatie\LaravelData\Optional;
use Src\Application\AppUser\Config\Data\UpdateOrganizationNotificationChannelData;
use Src\Application\Shared\Services\Notification\OrganizationNotificationChannelEmailVerificationService;
use Src\Application\Shared\Services\Notification\OrganizationNotificationChannelQuotaService;
use Src\Application\Shared\Services\Notification\OrganizationNotificationChannelValueService;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

readonly class UpdateOrganizationNotificationChannelService
{
    public function __construct(
        private OrganizationNotificationChannelQuotaService $quotaService,
        private OrganizationNotificationChannelValueService $valueService,
        private OrganizationNotificationChannelEmailVerificationService $emailVerificationService,
    ) {}

    public function handle(
        OrganizationNotificationChannel $channel,
        UpdateOrganizationNotificationChannelData $data,
    ): OrganizationNotificationChannel {
        $type = $data->channel_type ?? $channel->channel_type;
        $rawValue = ($data->channel_value !== null && trim($data->channel_value) !== '')
            ? $data->channel_value
            : $channel->channel_value;
        $value = $this->valueService->prepare($type, $rawValue);

        if ($type !== $channel->channel_type) {
            $this->quotaService->assertCanAdd($channel->organization_id, $type);
        }

        $this->valueService->assertAvailable($channel->organization_id, $type, $value, $channel->id);
        $this->emailVerificationService->rememberPrevious($channel, $value);

        $valueChanged = $type !== $channel->channel_type
            || strtolower($value) !== strtolower($channel->channel_value);

        $payload = [
            'channel_type' => $type,
            'channel_value' => $value,
        ];

        if ($valueChanged) {
            $payload['validated_at'] = $this->emailVerificationService->validatedAtFor($type, $value);
        }

        if (! $data->is_active instanceof Optional) {
            $payload['is_active'] = $data->is_active;
        }

        if ($type !== $channel->channel_type) {
            $payload['priority'] = $this->valueService->nextPriority($channel->organization_id, $type);
        }

        $channel->update($payload);
        $channel->refresh();

        if ($valueChanged) {
            $this->emailVerificationService->sendIfPending($channel);
        }

        return $channel;
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Notification\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

class OrganizationNotificationChannelResource extends Resource
{
    public function __construct(
        public string $id,
        public string $organization_id,
        public string $channel_type,
        public string $channel_value,
        public bool $is_active,
        public bool $is_validated,
        public int $priority,
    ) {}

    public static function fromModel(OrganizationNotificationChannel $channel): self
    {
        return new self(
            id: $channel->id,
            organization_id: $channel->organization_id,
            channel_type: $channel->channel_type,
            channel_value: $channel->channel_value,
            is_active: (bool) $channel->is_active,
            is_validated: $channel->validated_at !== null,
            priority: (int) $channel->priority,
        );
    }
}

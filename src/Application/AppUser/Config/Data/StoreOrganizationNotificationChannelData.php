<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Config\Data;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\Notification\Enums\NotificationChannelType;

class StoreOrganizationNotificationChannelData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required, StringType, In([NotificationChannelType::Email->value, NotificationChannelType::WhatsApp->value, NotificationChannelType::Sms->value])]
        public readonly string $channel_type,

        #[Required, StringType, Max(255)]
        public readonly string $channel_value,

        #[BooleanType]
        public readonly bool $is_active = true,
    ) {}
}

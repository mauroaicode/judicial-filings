<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Config\Data;

use Spatie\LaravelData\Attributes\Validation\Sometimes;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class UpdateOrganizationNotificationChannelData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Sometimes]
        public readonly ?string $channel_type = null,

        #[Sometimes]
        public readonly ?string $channel_value = null,

        public readonly bool|Optional $is_active = new Optional,
    ) {}

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'channel_type' => ['sometimes', 'string', 'in:email,whatsapp,sms'],
            'channel_value' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

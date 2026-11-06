<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class UpdateOrganizationNotificationChannelLimitsData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public readonly int|null|Optional $email = new Optional,
        public readonly int|null|Optional $whatsapp = new Optional,
        public readonly int|null|Optional $sms = new Optional,
    ) {}

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        $rule = ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'];

        return [
            'email' => $rule,
            'whatsapp' => $rule,
            'sms' => $rule,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Organization\Data;

use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class OrganizationAiStatusData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public bool $is_ai_enabled,
    ) {}

    public static function attributes(): array
    {
        return [
            'is_ai_enabled' => __('data.is_ai_enabled'),
        ];
    }
}

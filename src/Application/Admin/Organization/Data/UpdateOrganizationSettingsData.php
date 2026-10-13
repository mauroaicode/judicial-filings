<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Data;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class UpdateOrganizationSettingsData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $max_active_processes = null,

        public readonly bool|Optional $can_view_action_documents = new Optional,
    ) {}

    public static function rules(): array
    {
        return [
            'can_view_action_documents' => ['sometimes', 'boolean'],
        ];
    }

    public static function attributes(): array
    {
        return [
            'max_active_processes' => __('data.max_active_processes'),
            'can_view_action_documents' => __('data.can_view_action_documents'),
        ];
    }
}

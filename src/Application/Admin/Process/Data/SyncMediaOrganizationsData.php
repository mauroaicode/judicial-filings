<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class SyncMediaOrganizationsData extends Data
{
    use TranslatableDataAttributesTrait;

    /**
     * @param  list<string>  $organization_ids
     */
    public function __construct(
        #[Required, ArrayType]
        public readonly array $organization_ids,
    ) {}

    public static function rules(): array
    {
        return [
            'organization_ids' => ['required', 'array', 'min:1'],
            'organization_ids.*' => ['required', 'uuid', 'exists:organizations,id'],
        ];
    }

    public static function attributes(): array
    {
        return [
            'organization_ids' => __('data.organization_ids'),
            'organization_ids.*' => __('data.organization_ids.*'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class AttachAdminProcessOrganizationsData extends Data
{
    use TranslatableDataAttributesTrait;

    /**
     * @param  list<string>  $organization_ids
     */
    public function __construct(
        #[Required, Min(1)]
        public readonly array $organization_ids,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'organization_ids' => ['required', 'array', 'min:1', 'max:50'],
            'organization_ids.*' => ['required', 'uuid', 'distinct', 'exists:organizations,id'],
        ];
    }
}

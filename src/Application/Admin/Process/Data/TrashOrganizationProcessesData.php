<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class TrashOrganizationProcessesData extends Data
{
    use TranslatableDataAttributesTrait;

    /**
     * @param  list<string>  $process_ids
     */
    public function __construct(
        #[Required, Exists('organizations', 'id')]
        public readonly string $organization_id,
        #[Required, ArrayType, Min(1)]
        public readonly array $process_ids,
    ) {}

    public static function rules(): array
    {
        return [
            'process_ids.*' => ['required', 'uuid', 'distinct', 'exists:processes,id'],
        ];
    }
}

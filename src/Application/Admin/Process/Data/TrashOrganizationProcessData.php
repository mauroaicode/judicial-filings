<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class TrashOrganizationProcessData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required, Exists('organizations', 'id')]
        public readonly string $organization_id,
    ) {}
}

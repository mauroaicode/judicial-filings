<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Data;

use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class OrganizationFilterData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
        public ?string $email = null,
        #[In(['active', 'inactive'])]
        public ?string $is_active = null,
        #[Date]
        public ?string $created_at_from = null,
        #[Date]
        public ?string $created_at_to = null,
    ) {}
}

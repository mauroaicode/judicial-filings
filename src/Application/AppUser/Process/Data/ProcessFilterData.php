<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Data;

use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class ProcessFilterData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public ?string $process_number = null,
        #[Date]
        public ?string $created_at = null,
        #[Date]
        public ?string $created_at_from = null,
        #[Date]
        public ?string $created_at_to = null,
        #[Date]
        public ?string $process_date = null,
        #[Date]
        public ?string $process_date_from = null,
        #[Date]
        public ?string $process_date_to = null,
        #[In(['active', 'inactive'])]
        public ?string $status = null,
        public mixed $is_private = null,
        public mixed $has_multiple_instances = null,
    ) {}
}

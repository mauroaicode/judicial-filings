<?php

declare(strict_types=1);

namespace Src\Application\Shared\Data;

use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class ProcessFilterData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public ?string $process_number = null,
        public ?string $court = null,
        public ?string $process_class = null,
        public ?string $plaintiff = null,
        public ?string $defendant = null,
        public ?string $organization = null,
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
        #[Date]
        public ?string $last_api_update_from = null,
        #[Date]
        public ?string $last_api_update_to = null,
        #[In(['active', 'inactive'])]
        public ?string $status = null,
        /** Admin uses judicial `processes.status`; AppUser keeps pivot subscription. */
        public bool $status_on_process_table = false,
        public mixed $has_multiple_instances = null,
        #[In(['plaintiff', 'defendant', 'none'])]
        public ?string $lawyer_role = null,
        #[In(['red', 'yellow', 'green', 'none'])]
        public ?string $severity_color = null,
        /** `private` | `public`; omit for no filter (admin and app-user lists). */
        #[In(['private', 'public'])]
        public ?string $privacy = null,
    ) {}
}

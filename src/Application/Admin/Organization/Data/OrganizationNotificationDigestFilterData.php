<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Data;

use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class OrganizationNotificationDigestFilterData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public ?string $process_number = null,
        #[Date]
        public ?string $registration_date_from = null,
        #[Date]
        public ?string $registration_date_to = null,
        #[Date]
        public ?string $action_date_from = null,
        #[Date]
        public ?string $action_date_to = null,
        #[Date]
        public ?string $created_at_from = null,
        #[Date]
        public ?string $created_at_to = null,
        #[Date]
        public ?string $term_start_date_from = null,
        #[Date]
        public ?string $term_start_date_to = null,
        #[Date]
        public ?string $term_end_date_from = null,
        #[Date]
        public ?string $term_end_date_to = null,
        public int $per_page = 20,
    ) {}
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\Notification\Data;

use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class NotificationDigestFilterData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Nullable, Uuid, Exists('organizations', 'id')]
        public ?string $organization_id = null,
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
        #[Nullable, In(['0', '1'])]
        public ?string $is_viewed = null,
        public int $per_page = 20,
    ) {}
}

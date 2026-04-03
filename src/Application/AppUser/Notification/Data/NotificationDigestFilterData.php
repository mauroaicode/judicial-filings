<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Data;

use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class NotificationDigestFilterData extends Data
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

    public function hasCriterialFilters(): bool
    {
        return $this->process_number ||
            $this->registration_date_from || $this->registration_date_to ||
            $this->action_date_from || $this->action_date_to ||
            $this->created_at_from || $this->created_at_to ||
            $this->term_start_date_from || $this->term_start_date_to ||
            $this->term_end_date_from || $this->term_end_date_to;
    }
}

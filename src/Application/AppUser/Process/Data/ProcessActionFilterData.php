<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Data;

use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class ProcessActionFilterData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Date]
        public ?string $action_date_from = null,
        #[Date]
        public ?string $action_date_to = null,
        #[Date]
        public ?string $registration_date_from = null,
        #[Date]
        public ?string $registration_date_to = null,
        public ?string $search = null,
        /** Slug of alert keyword to filter by (e.g. sentencia, consulta, fijacion_estado). Only actions that have this keyword are returned. */
        public ?string $alert_slug = null,

        // New Smart Filters
        public ?string $process_number = null,
        #[Date]
        public ?string $date_from = null,
        #[Date]
        public ?string $date_to = null,
        public ?string $alert_level = null,
        public ?string $lawyer_role = null,
    ) {}
}

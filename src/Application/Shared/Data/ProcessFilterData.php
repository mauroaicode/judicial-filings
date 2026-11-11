<?php

declare(strict_types=1);

namespace Src\Application\Shared\Data;

use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;

class ProcessFilterData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public ?string $process_number = null,
        public ?string $court = null,
        public ?string $process_class = null,
        public ?string $plaintiff = null,
        public ?string $defendant = null,
        /** Partial match on organization name (admin / shared lists). */
        public ?string $organization = null,
        /** Exact organization UUID (admin list). */
        public ?string $organization_id = null,
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
        #[In([
            OrganizationProcessStatus::ACTIVE->value,
            OrganizationProcessStatus::INACTIVE->value,
            OrganizationProcessStatus::PENDING_HEARING->value,
            OrganizationProcessStatus::SUSPENDED_INSOLVENCY->value,
            OrganizationProcessStatus::SUSPENDED_PAYMENT_AGREEMENT->value,
            OrganizationProcessStatus::SUSPENDED->value,
        ])]
        public ?string $status = null,
        /** Admin uses judicial `processes.status`; AppUser keeps pivot subscription. */
        public bool $status_on_process_table = false,
        public mixed $has_multiple_instances = null,
        #[In(['plaintiff', 'defendant', 'none'])]
        public ?string $lawyer_role = null,
        #[In([
            'red',
            'yellow',
            'green',
            'none',
            OrganizationProcessStatus::PENDING_HEARING->value,
            OrganizationProcessStatus::SUSPENDED_INSOLVENCY->value,
            OrganizationProcessStatus::SUSPENDED_PAYMENT_AGREEMENT->value,
            OrganizationProcessStatus::SUSPENDED->value,
        ])]
        public ?string $severity_color = null,
        /** `private` | `public`; omit for no filter (admin and app-user lists). */
        #[In(['private', 'public'])]
        public ?string $privacy = null,
        /** `1`/`true` = only radicados given de alta via a registered manual request. */
        public mixed $alta_manual = null,
        /** `1`/`true` = has actuaciones withdrawn on Rama; `0`/`false` = none. Omit for all. */
        public mixed $removed_from_judicial_branch = null,
    ) {}

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
        ];
    }
}

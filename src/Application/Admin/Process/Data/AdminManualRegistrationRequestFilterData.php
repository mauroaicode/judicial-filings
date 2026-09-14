<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\Process\Enums\ManualRegistrationRequestReason;
use Src\Domain\Process\Enums\ManualRegistrationRequestStatus;

class AdminManualRegistrationRequestFilterData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[In([
            ManualRegistrationRequestStatus::Pending->value,
            ManualRegistrationRequestStatus::Registered->value,
            ManualRegistrationRequestStatus::Rejected->value,
        ])]
        public ?string $status = ManualRegistrationRequestStatus::Pending->value,
        #[In([
            ManualRegistrationRequestReason::NotFound->value,
            ManualRegistrationRequestReason::Private->value,
            ManualRegistrationRequestReason::AllPrivate->value,
        ])]
        public ?string $reason = null,
        public ?string $organization = null,
        public ?string $process_number = null,
        #[Min(1), Max(100)]
        public int $per_page = 20,
    ) {}
}

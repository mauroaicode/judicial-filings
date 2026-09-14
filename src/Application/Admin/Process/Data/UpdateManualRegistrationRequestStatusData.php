<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\Process\Enums\ManualRegistrationRequestStatus;

class UpdateManualRegistrationRequestStatusData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[In([
            ManualRegistrationRequestStatus::Registered->value,
            ManualRegistrationRequestStatus::Rejected->value,
        ])]
        public string $status,
    ) {}
}

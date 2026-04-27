<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Auth\Data;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class VerifyPasswordData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required, StringType, Min(1)]
        public readonly string $password,
    ) {}
}

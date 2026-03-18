<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Auth\Data;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class ForgotPasswordData extends Data
{
    public function __construct(
        #[Required, StringType]
        public readonly string $identification,
    ) {}
}

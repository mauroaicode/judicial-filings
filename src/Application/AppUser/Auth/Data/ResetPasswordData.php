<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Auth\Data;

use Spatie\LaravelData\Attributes\Validation\Confirmed;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class ResetPasswordData extends Data
{
    public function __construct(
        #[Required, StringType]
        public readonly string $identification,

        #[Required, StringType]
        public readonly string $token,

        #[Required, StringType, Min(8), Confirmed]
        public readonly string $password,

        #[Required, StringType]
        public readonly string $password_confirmation,
    ) {}
}

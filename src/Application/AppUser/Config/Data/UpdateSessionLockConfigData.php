<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Config\Data;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class UpdateSessionLockConfigData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required, BooleanType]
        public bool $session_lock_enabled,

        #[Required, IntegerType, Min(1)]
        public int $session_lock_timeout,
    ) {}
}

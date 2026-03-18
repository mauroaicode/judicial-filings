<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Data;

use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class StoreProcessData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required, Regex('/^\d{23}$/')]
        public readonly string $process_number,
    ) {}
}

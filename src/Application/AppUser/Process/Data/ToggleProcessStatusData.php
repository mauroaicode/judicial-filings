<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Data;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class ToggleProcessStatusData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required, BooleanType]
        public readonly bool $is_active,
    ) {}
}

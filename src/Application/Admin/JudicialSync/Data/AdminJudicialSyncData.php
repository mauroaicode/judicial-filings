<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Data;

use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class AdminJudicialSyncData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Nullable, Regex('/^\d{23}$/')]
        public ?string $radicado = null,
    ) {}
}

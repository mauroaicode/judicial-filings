<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Data;

use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class ProcessImportFilterData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public ?string $status = null,
        public int $per_page = 15,
    ) {}
}

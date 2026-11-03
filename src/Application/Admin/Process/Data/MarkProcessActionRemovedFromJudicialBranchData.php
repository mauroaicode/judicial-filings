<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class MarkProcessActionRemovedFromJudicialBranchData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public bool $notify_organizations = false,
        #[Max(2000)]
        public ?string $reason = null,
    ) {}
}

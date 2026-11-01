<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

class MarkProcessActionRemovedFromJudicialBranchData extends Data
{
    public function __construct(
        public bool $notify_organizations = false,
        #[Max(2000)]
        public ?string $reason = null,
    ) {}
}

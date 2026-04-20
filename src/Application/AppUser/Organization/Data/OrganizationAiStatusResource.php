<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Organization\Data;

use Spatie\LaravelData\Data;

class OrganizationAiStatusResource extends Data
{
    public function __construct(
        public bool $is_ai_enabled,
    ) {}
}

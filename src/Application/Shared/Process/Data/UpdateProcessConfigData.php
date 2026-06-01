<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Data;

use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\Process\Enums\ProcessLawyerRole;

class UpdateProcessConfigData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public ?ProcessLawyerRole $lawyer_role = null,
    ) {}
}

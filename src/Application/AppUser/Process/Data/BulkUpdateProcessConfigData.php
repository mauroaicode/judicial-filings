<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Data;

use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\Process\Enums\ProcessLawyerRole;

class BulkUpdateProcessConfigData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[ArrayType, Min(1)]
        /** @var array<string> List of process UUIDs */
        public array $process_ids,

        public ProcessLawyerRole $lawyer_role,
    ) {}
}

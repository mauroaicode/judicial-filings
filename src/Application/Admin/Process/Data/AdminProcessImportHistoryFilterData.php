<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\Process\Enums\ProcessImportBatchStatus;

class AdminProcessImportHistoryFilterData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public ?string $organization = null,
        public ?string $file_name = null,
        #[In([
            ProcessImportBatchStatus::PROCESSING->value,
            ProcessImportBatchStatus::COMPLETED->value,
            ProcessImportBatchStatus::FAILED->value,
        ])]
        public ?string $status = null,
        public mixed $has_errors = null,
        #[Date]
        public ?string $created_at_from = null,
        #[Date]
        public ?string $created_at_to = null,
        public int $per_page = 15,
    ) {}
}

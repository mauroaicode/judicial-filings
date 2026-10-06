<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\Process\Enums\ProcessExportStatus;

class AdminProcessExportHistoryFilterData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public ?string $organization = null,
        public ?string $organization_id = null,
        #[In([
            ProcessExportStatus::Pending->value,
            ProcessExportStatus::Processing->value,
            ProcessExportStatus::Completed->value,
            ProcessExportStatus::Failed->value,
        ])]
        public ?string $status = null,
        #[Date]
        public ?string $created_at_from = null,
        #[Date]
        public ?string $created_at_to = null,
        public int $per_page = 15,
    ) {}
}

<?php

declare(strict_types=1);

namespace Src\Domain\Task\Data;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\Task\Enums\TaskStatus;

class UpdateTaskStatusData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required, In([
            TaskStatus::PENDING->value,
            TaskStatus::COMPLETED->value,
            TaskStatus::DRAFT->value,
        ])]
        public string $status,
    ) {}
}

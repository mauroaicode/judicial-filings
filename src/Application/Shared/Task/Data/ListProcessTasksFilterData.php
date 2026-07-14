<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Data;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Enums\TaskType;

class ListProcessTasksFilterData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[In([
            TaskStatus::PENDING->value,
            TaskStatus::COMPLETED->value,
            TaskStatus::DRAFT->value,
        ])]
        public ?string $status = null,

        #[In([
            TaskType::GENERAL->value,
            TaskType::SUSPENSION->value,
        ])]
        public ?string $type = null,

        #[IntegerType, Min(1), Max(100)]
        public int $per_page = 20,
    ) {}
}

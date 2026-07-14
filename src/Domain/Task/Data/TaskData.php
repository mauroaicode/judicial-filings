<?php

declare(strict_types=1);

namespace Src\Domain\Task\Data;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\RequiredIf;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\Task\Enums\TaskType;

class TaskData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public ?string $id,

        #[Required, StringType]
        public string $title,

        #[Required, StringType]
        public string $description,

        #[Required, Date]
        public string $due_date,

        #[Required, IntegerType, Min(0)]
        public int $reminder_days,

        #[Nullable, In([
            TaskType::GENERAL->value,
            TaskType::SUSPENSION->value,
        ])]
        public ?string $type,

        #[Required, BooleanType]
        public bool $is_admin,

        #[RequiredIf('type', TaskType::SUSPENSION->value), Nullable, Uuid]
        public ?string $process_id,

        #[Required, Uuid]
        public ?string $organization_id,

        public ?string $created_at,
    ) {}
}

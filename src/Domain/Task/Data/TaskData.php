<?php

declare(strict_types=1);

namespace Src\Domain\Task\Data;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class TaskData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public ?string $id,

        #[Required, StringType]
        public string $title,

        #[Required, StringType]
        public string $description,

        #[Required, BooleanType]
        public bool $is_admin,

        #[Uuid]
        public ?string $process_id,

        #[Required, Uuid]
        public ?string $organization_id,

        public ?string $created_at,
    ) {}
}

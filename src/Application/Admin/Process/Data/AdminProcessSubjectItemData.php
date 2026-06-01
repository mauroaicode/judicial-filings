<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class AdminProcessSubjectItemData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Nullable, Uuid]
        public ?string $id,
        #[Required, StringType]
        public string $subject_type,
        #[Required, StringType]
        public string $name_or_business_name,
    ) {}
}

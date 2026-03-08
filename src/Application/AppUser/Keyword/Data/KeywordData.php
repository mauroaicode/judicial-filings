<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Keyword\Data;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class KeywordData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public string $name,
        public string $keyword,
        #[In(['active', 'inactive'])]
        public string $status = 'active',
    ) {}
}

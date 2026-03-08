<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Keyword\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\Keyword\Models\Keyword;

class KeywordResource extends Resource
{
    public function __construct(
        public string $id,
        public string $name,
        public string $keyword,
        public string $status,
        public string $created_at,
    ) {}

    public static function fromModel(Keyword $keyword): self
    {
        return new self(
            id: $keyword->id,
            name: $keyword->name,
            keyword: $keyword->keyword,
            status: $keyword->status->value,
            created_at: $keyword->created_at->format('Y-m-d H:i:s'),
        );
    }
}

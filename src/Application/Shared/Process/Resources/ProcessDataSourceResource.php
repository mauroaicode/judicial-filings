<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\Process\Models\ProcessDataSource;

class ProcessDataSourceResource extends Resource
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public bool $is_active,
    ) {}

    public static function fromModel(ProcessDataSource $source): self
    {
        return new self(
            id: $source->id,
            slug: $source->slug,
            name: $source->name,
            is_active: $source->is_active,
        );
    }
}

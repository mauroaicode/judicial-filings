<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\Process\Models\Process;

class OrganizationProcessResource extends Resource
{
    public function __construct(
        public string $id,
        public string $number,
        public string $despacho,
    ) {}

    public static function fromModel(Process $process): self
    {
        return new self(
            id: $process->id,
            number: $process->process_number,
            despacho: $process->court,
        );
    }
}

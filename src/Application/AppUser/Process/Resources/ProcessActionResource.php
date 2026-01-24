<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\Process\Models\ProcessAction;

class ProcessActionResource extends Resource
{
    public function __construct(
        public string $id,
        public int $action_registration_id,
        public string $action_date,
        public string $action,
        public ?string $annotation,
        public ?string $start_date,
        public ?string $end_date,
        public string $registration_date,
    ) {}

    public static function fromModel(ProcessAction $action): self
    {
        return new self(
            id: $action->id,
            action_registration_id: $action->action_registration_id,
            action_date: $action->action_date->format('Y-m-d'),
            action: $action->action,
            annotation: $action->annotation,
            start_date: $action->start_date?->format('Y-m-d'),
            end_date: $action->end_date?->format('Y-m-d'),
            registration_date: $action->registration_date->format('Y-m-d'),
        );
    }
}

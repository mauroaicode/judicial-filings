<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Models\Process;

class ProcessIndexResource extends Resource
{
    public function __construct(
        public string $id,
        public string $process_number,
        public string $court,
        public string $department,
        public string $process_type,
        public string $process_class,
        public ?string $subclass_process,
        public string $process_date,
        public ?string $last_activity_date,
        public ?string $location,
        public bool $is_private,
        public bool $has_multiple_instances,
        public string $status_label,
        public string $created_at,
    ) {}

    public static function fromModel(Process $process, string $organizationId): self
    {
        $isActive = false;
        $createdAt = $process->created_at;

        if ($process->relationLoaded('organizations')) {
            $organization = $process->organizations->firstWhere('id', $organizationId);
            if ($organization && $organization->pivot) {
                $isActive = (bool) $organization->pivot->is_active;
                if ($organization->pivot->created_at) {
                    $createdAt = $organization->pivot->created_at;
                }
            }
        }

        $status = OrganizationProcessStatus::fromBoolean($isActive);

        return new self(
            id: $process->id,
            process_number: $process->process_number,
            court: $process->court,
            department: $process->department,
            process_type: $process->process_type,
            process_class: $process->process_class,
            subclass_process: $process->subclass_process,
            process_date: $process->process_date->format('Y-m-d'),
            last_activity_date: $process->last_activity_date?->format('Y-m-d'),
            location: $process->location,
            is_private: $process->is_private,
            has_multiple_instances: $process->has_multiple_instances,
            status_label: $status->getLabel(),
            created_at: $createdAt->format('Y-m-d H:i:s'),
        );
    }
}

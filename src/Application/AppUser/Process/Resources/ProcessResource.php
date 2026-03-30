<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\Process\Models\Process;

class ProcessResource extends Resource
{
    public function __construct(
        public string $id,
        public int $process_id,
        public string $process_number,
        public string $court,
        public string $department,
        public string $process_type,
        public string $process_class,
        public ?string $subclass_process,
        public ?string $litigants,
        public string $process_date,
        public ?string $last_activity_date,
        public ?string $location,
        public ?string $filing_content,
        public bool $is_private,
        public bool $has_multiple_instances,
        public ?string $interest_date,
        public ?string $term_start_date,
        public ?string $term_end_date,
        public bool $is_active,
        public ?string $speaker,
    ) {}

    public static function fromModel(Process $process, string $organizationId = ''): self
    {
        $isActive = true;
        $interestDate = null;
        $termStartDate = null;
        $termEndDate = null;

        if ($organizationId !== '' && $process->relationLoaded('organizations')) {
            $organization = $process->organizations->firstWhere('id', $organizationId);
            if ($organization && $organization->pivot) {
                $isActive = (bool) $organization->pivot->is_active;
                $interestDate = $organization->pivot->interest_date?->toDateString();
                $termStartDate = $organization->pivot->term_start_date?->toDateString();
                $termEndDate = $organization->pivot->term_end_date?->toDateString();
            }
        }

        return new self(
            id: $process->id,
            process_id: $process->process_id,
            process_number: $process->process_number,
            court: $process->court,
            department: $process->department,
            process_type: $process->process_type,
            process_class: $process->process_class,
            subclass_process: $process->subclass_process,
            litigants: $process->litigants,
            process_date: $process->process_date->toDateString(),
            last_activity_date: $process->last_activity_date?->toDateString(),
            location: $process->location,
            filing_content: $process->filing_content,
            is_private: $process->is_private,
            has_multiple_instances: $process->has_multiple_instances,
            interest_date: $interestDate,
            term_start_date: $termStartDate,
            term_end_date: $termEndDate,
            is_active: $isActive,
            speaker: $process->speaker,
        );
    }
}

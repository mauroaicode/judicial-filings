<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Models\Process;

class ProcessIndexResource extends Resource
{
    public function __construct(
        public int $index,
        public string $id,
        public string $process_number,
        public string $court,
        public string $process_class,
        public ?string $subclass_process,
        public string $process_date,
        public ?string $last_activity_date,
        public bool $is_private,
        public bool $has_multiple_instances,
        public string $status_label,
        public string $created_at,
        public ?string $plaintiff,
        public ?string $defendant,
    ) {}

    public static function fromModel(Process $process, string $organizationId, int $index = 0): self
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

        $plaintiff = null;
        $defendant = null;

        if ($process->relationLoaded('subjects')) {
            $subjects = $process->subjects;

            $plaintiffs = $subjects->where('subject_type', 'Demandante');
            if ($plaintiffs->isNotEmpty()) {
                $firstPlaintiff = $plaintiffs->first();
                $plaintiffName = StrParseHelper::toTitleCase($firstPlaintiff->name_or_business_name) ?? '';
                $plaintiff = $plaintiffs->count() > 1 ? $plaintiffName.' (+'.($plaintiffs->count() - 1).')' : $plaintiffName;
            }

            $defendants = $subjects->where('subject_type', 'Demandado');
            if ($defendants->isNotEmpty()) {
                $firstDefendant = $defendants->first();
                $defendantName = StrParseHelper::toTitleCase($firstDefendant->name_or_business_name) ?? '';
                $defendant = $defendants->count() > 1 ? $defendantName.' (+'.($defendants->count() - 1).')' : $defendantName;
            }
        }

        return new self(
            index: $index,
            id: $process->id,
            process_number: $process->process_number,
            court: StrParseHelper::toTitleCase($process->court) ?? '',
            process_class: StrParseHelper::toTitleCase($process->process_class) ?? '',
            subclass_process: $process->subclass_process ? StrParseHelper::toTitleCase($process->subclass_process) : null,
            process_date: $process->process_date->format('Y-m-d'),
            last_activity_date: $process->last_api_update?->format('Y-m-d h:i:s A'),
            is_private: $process->is_private,
            has_multiple_instances: $process->has_multiple_instances,
            status_label: $status->getLabel(),
            created_at: $createdAt->format('Y-m-d'),
            plaintiff: $plaintiff,
            defendant: $defendant,
        );
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
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
        public string $term_start_date,
        public string $term_end_date,
        public ?string $plaintiff,
        public ?string $defendant,
        /** @var list<string> Full list of plaintiff names for tooltip */
        public array $plaintiffs,
        /** @var list<string> Full list of defendant names for tooltip */
        public array $defendants,
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
        $plaintiffsList = [];
        $defendantsList = [];

        if ($process->relationLoaded('subjects')) {
            $subjects = $process->subjects;

            $plaintiffsCollection = $subjects->where('subject_type', 'Demandante');
            if ($plaintiffsCollection->isNotEmpty()) {
                $firstPlaintiff = $plaintiffsCollection->first();
                $plaintiffName = StrParseHelper::toTitleCase($firstPlaintiff->name_or_business_name) ?? '';
                $plaintiff = $plaintiffsCollection->count() > 1 ? $plaintiffName.' (+'.($plaintiffsCollection->count() - 1).')' : $plaintiffName;
                $plaintiffsList = $plaintiffsCollection->map(fn ($s) => StrParseHelper::toTitleCase($s->name_or_business_name) ?? '')->values()->all();
            }

            $defendantsCollection = $subjects->where('subject_type', 'Demandado');
            if ($defendantsCollection->isNotEmpty()) {
                $firstDefendant = $defendantsCollection->first();
                $defendantName = StrParseHelper::toTitleCase($firstDefendant->name_or_business_name) ?? '';
                $defendant = $defendantsCollection->count() > 1 ? $defendantName.' (+'.($defendantsCollection->count() - 1).')' : $defendantName;
                $defendantsList = $defendantsCollection->map(fn ($s) => StrParseHelper::toTitleCase($s->name_or_business_name) ?? '')->values()->all();
            }
        }

        return new self(
            index: $index,
            id: $process->id,
            process_number: $process->process_number,
            court: StrParseHelper::toTitleCase($process->court) ?? '',
            process_class: StrParseHelper::toTitleCase($process->process_class) ?? '',
            subclass_process: $process->subclass_process ? StrParseHelper::toTitleCase($process->subclass_process) : null,
            process_date: DateFormatHelper::formatDate($process->process_date),
            last_activity_date: $process->last_activity_date ? DateFormatHelper::formatDate($process->last_activity_date) : null,
            is_private: $process->is_private,
            has_multiple_instances: $process->has_multiple_instances,
            status_label: $status->getLabel(),
            created_at: DateFormatHelper::formatDate($createdAt),
            term_start_date: '-',
            term_end_date: '-',
            plaintiff: $plaintiff,
            defendant: $defendant,
            plaintiffs: $plaintiffsList,
            defendants: $defendantsList,
        );
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\ProcessAlertLevelHelper;
use Src\Application\Shared\Helpers\ProcessSubjectSummaryHelper;
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
        public ?int $plaintiffs_count = null,
        public ?int $defendants_count = null,
        public ?int $others_count = null,
        public ?int $subjects_count = null,
        public ?string $other_subject = null,
        /** @var list<string> Full list of other subject names for tooltip */
        public array $others = [],
        public ?string $alert_level = null,
        public ?string $lawyer_role = null,
    ) {}

    public static function fromModel(Process $process, string $organizationId, int $index = 0): self
    {
        $isActive = false;
        $createdAt = $process->created_at;
        $alertLevel = null;
        $lawyerRoleLabel = null;
        $lawyerRole = null;

        if ($process->relationLoaded('organizations')) {
            $organization = $process->organizations->firstWhere('id', $organizationId);
            if ($organization && $organization->pivot) {
                $isActive = (bool) $organization->pivot->is_active;
                if ($organization->pivot->created_at) {
                    $createdAt = $organization->pivot->created_at;
                }

                $alertLevel = $organization->pivot->inactivity_alert_level;

                $role = $organization->pivot->lawyer_role;
                if (is_string($role)) {
                    $role = \Src\Domain\Process\Enums\ProcessLawyerRole::tryFrom($role);
                }

                $lawyerRole = $role;
                $lawyerRoleLabel = $role instanceof \Src\Domain\Process\Enums\ProcessLawyerRole ? $role->getLabel() : (string) $role;
            }
        }

        $alertLevel = ProcessAlertLevelHelper::resolve(
            $alertLevel,
            $process->last_activity_date,
            $lawyerRole instanceof \Src\Domain\Process\Enums\ProcessLawyerRole ? $lawyerRole : null,
        );

        $status = OrganizationProcessStatus::fromBoolean($isActive);

        $subjectSummary = $process->relationLoaded('subjects')
            ? ProcessSubjectSummaryHelper::summarize($process->subjects)
            : ProcessSubjectSummaryHelper::summarize(collect());

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
            plaintiff: $subjectSummary['plaintiff'],
            defendant: $subjectSummary['defendant'],
            plaintiffs: $subjectSummary['plaintiffs'],
            defendants: $subjectSummary['defendants'],
            plaintiffs_count: $subjectSummary['plaintiffs_count'],
            defendants_count: $subjectSummary['defendants_count'],
            others_count: $subjectSummary['others_count'],
            subjects_count: $subjectSummary['subjects_count'],
            other_subject: $subjectSummary['other_subject'],
            others: $subjectSummary['others'],
            alert_level: $alertLevel,
            lawyer_role: $lawyerRoleLabel,
        );
    }
}

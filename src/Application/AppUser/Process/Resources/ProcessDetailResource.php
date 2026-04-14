<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Models\Process;

class ProcessDetailResource extends Resource
{
    public function __construct(
        public string $id,
        public int $process_id,
        public string $process_number,
        public string $court,
        public ?string $speaker,
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
        public ?string $last_api_update,
        public string $status_label,
        public string $created_at,
        public string $updated_at,
        public string $term_start_date,
        public string $term_end_date,
        public ?string $alert_level = null,
        public ?string $lawyer_role = null,
    ) {}

    public static function fromModel(Process $process, string $organizationId): self
    {
        $isActive = false;
        $createdAt = $process->created_at;
        $alertLevel = null;
        $lawyerRoleLabel = null;

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

                $lawyerRoleLabel = $role instanceof \Src\Domain\Process\Enums\ProcessLawyerRole ? $role->getLabel() : (string) $role;
            }
        }

        $status = OrganizationProcessStatus::fromBoolean($isActive);

        return new self(
            id: $process->id,
            process_id: $process->process_id,
            process_number: $process->process_number,
            court: StrParseHelper::toTitleCase($process->court) ?? '',
            speaker: $process->speaker ? StrParseHelper::toTitleCase($process->speaker) : null,
            department: StrParseHelper::toTitleCase($process->department) ?? '',
            process_type: StrParseHelper::toTitleCase($process->process_type) ?? '',
            process_class: StrParseHelper::toTitleCase($process->process_class) ?? '',
            subclass_process: $process->subclass_process ? StrParseHelper::toTitleCase($process->subclass_process) : null,
            litigants: $process->litigants,
            process_date: DateFormatHelper::formatDate($process->process_date),
            last_activity_date: $process->last_activity_date ? DateFormatHelper::formatDateTimeWithDayOfWeek($process->last_activity_date) : null,
            location: $process->location ? StrParseHelper::toTitleCase($process->location) : null,
            filing_content: $process->filing_content,
            is_private: $process->is_private,
            has_multiple_instances: $process->has_multiple_instances,
            last_api_update: $process->last_api_update ? DateFormatHelper::formatDateTimeWithDayOfWeek($process->last_api_update) : null,
            status_label: $status->getLabel(),
            created_at: DateFormatHelper::formatDateTime($createdAt),
            updated_at: DateFormatHelper::formatDateTime($process->updated_at),
            term_start_date: '-',
            term_end_date: '-',
            alert_level: $alertLevel,
            lawyer_role: $lawyerRoleLabel,
        );
    }
}

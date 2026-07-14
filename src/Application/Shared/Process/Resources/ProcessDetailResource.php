<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\ProcessAlertLevelHelper;
use Src\Application\Shared\Helpers\ProcessSemaphoreHelper;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Models\Process;

class ProcessDetailResource extends Resource
{
    public function __construct(
        public string $id,
        public ?int $process_id,
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
        public string $status,
        public string $status_label,
        /** @var array{paused: bool, reason: string|null, message: string|null} */
        public array $semaphore,
        public string $created_at,
        public string $updated_at,
        public string $term_start_date,
        public string $term_end_date,
        public ?string $alert_level = null,
        public ?string $lawyer_role = null,
        public int $tasks_count = 0,
    ) {}

    /**
     * @param  bool  $statusActiveIfAnyOrganization  When true, status_label is Activo if any linked organization has an active pivot (admin detail).
     */
    public static function fromModel(
        Process $process,
        string $organizationId,
        bool $statusActiveIfAnyOrganization = false,
    ): self {
        $createdAt = $process->created_at;
        $alertLevel = null;
        $lawyerRoleLabel = null;
        $lawyerRole = null;
        $organization = null;

        if ($process->relationLoaded('organizations')) {
            $organization = $process->organizations->firstWhere('id', $organizationId);

            if ($organization && $organization->pivot) {
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

        $status = $statusActiveIfAnyOrganization && $process->relationLoaded('organizations')
            ? self::resolveStatusAcrossOrganizations($process)
            : OrganizationProcessStatus::fromPivot($organization?->pivot);

        $semaphore = ProcessSemaphoreHelper::resolve($status);

        $calculatedAlertLevel = ProcessAlertLevelHelper::resolve(
            $alertLevel,
            $process->last_activity_date,
            $lawyerRole instanceof \Src\Domain\Process\Enums\ProcessLawyerRole ? $lawyerRole : null,
        );

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
            status: $status->value,
            status_label: $status->getLabel(),
            semaphore: $semaphore,
            created_at: DateFormatHelper::formatDateTime($createdAt),
            updated_at: DateFormatHelper::formatDateTime($process->updated_at),
            term_start_date: '-',
            term_end_date: '-',
            alert_level: ProcessSemaphoreHelper::resolveAlertLevel($status, $calculatedAlertLevel),
            lawyer_role: $lawyerRoleLabel,
            tasks_count: (int) ($process->tasks_count ?? 0),
        );
    }

    private static function resolveStatusAcrossOrganizations(Process $process): OrganizationProcessStatus
    {
        $statuses = $process->organizations
            ->map(fn ($org): \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus => OrganizationProcessStatus::fromPivot($org->pivot));

        if ($statuses->contains(OrganizationProcessStatus::ACTIVE)) {
            return OrganizationProcessStatus::ACTIVE;
        }

        if ($statuses->contains(OrganizationProcessStatus::SUSPENDED)) {
            return OrganizationProcessStatus::SUSPENDED;
        }

        return OrganizationProcessStatus::INACTIVE;
    }
}

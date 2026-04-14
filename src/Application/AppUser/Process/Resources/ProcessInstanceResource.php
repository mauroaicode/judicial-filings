<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Models\Process;

class ProcessInstanceResource extends Resource
{
    public function __construct(
        public string $id,
        public string $court,
        public int $actions_count,
        public ?string $last_activity_date,
        public ?string $last_api_update,
        public string $status_label,
        public ?string $lawyer_role,
        public ?string $inactivity_alert_level,
    ) {}

    public static function fromModel(Process $process, string $organizationId): self
    {
        $isActive = false;
        $lawyerRole = null;
        $alertLevel = null;

        if ($process->relationLoaded('organizations')) {
            $organization = $process->organizations->firstWhere('id', $organizationId);
            if ($organization && $organization->pivot) {
                $isActive = (bool) $organization->pivot->is_active;
                $lawyerRole = $organization->pivot->lawyer_role;
                $alertLevel = $organization->pivot->inactivity_alert_level;
            }
        }

        $status = OrganizationProcessStatus::fromBoolean($isActive);

        return new self(
            id: $process->id,
            court: StrParseHelper::toTitleCase($process->court) ?? '',
            actions_count: $process->actions_count ?? 0,
            last_activity_date: $process->last_activity_date
                ? DateFormatHelper::formatDateTimeWithDayOfWeek($process->last_activity_date)
                : null,
            last_api_update: $process->last_api_update
                ? DateFormatHelper::formatDateTime($process->last_api_update)
                : null,
            status_label: $status->getLabel(),
            lawyer_role: $lawyerRole,
            inactivity_alert_level: $alertLevel,
        );
    }
}

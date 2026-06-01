<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\ProcessAlertLevelHelper;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Domain\Organization\Enums\OrganizationType;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\Process;

class AdminProcessOrganizationResource extends Resource
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public string $type_label,
        public ?string $lawyer_role,
        public ?string $lawyer_role_label,
        public string $status,
        public string $status_label,
        public string $interest_date,
        public ?string $inactivity_alert_level,
        public ?string $alert_level,
    ) {}

    public static function fromOrganizationAndProcess(Organization $organization, Process $process): self
    {
        $pivot = $organization->pivot;

        $lawyerRole = null;
        if ($pivot && $pivot->lawyer_role) {
            $lawyerRole = $pivot->lawyer_role instanceof ProcessLawyerRole
                ? $pivot->lawyer_role
                : ProcessLawyerRole::tryFrom((string) $pivot->lawyer_role);
        }

        $storedAlertLevel = $pivot ? $pivot->inactivity_alert_level : null;
        $isActive = $pivot && (bool) $pivot->is_active;
        $status = OrganizationProcessStatus::fromBoolean($isActive);

        $orgType = OrganizationType::tryFrom((string) $organization->type);

        return new self(
            id: $organization->id,
            name: StrParseHelper::toTitleCase($organization->name) ?? $organization->name,
            type: $orgType ? $orgType->value : (string) $organization->type,
            type_label: $orgType?->getLabel() ?? (string) $organization->type,
            lawyer_role: $lawyerRole?->value,
            lawyer_role_label: $lawyerRole?->getLabel(),
            status: $status->value,
            status_label: $status->getLabel(),
            interest_date: DateFormatHelper::formatDate($pivot ? $pivot->interest_date : now()),
            inactivity_alert_level: $storedAlertLevel,
            alert_level: ProcessAlertLevelHelper::resolve(
                $storedAlertLevel,
                $process->last_activity_date,
                $lawyerRole,
            ),
        );
    }
}

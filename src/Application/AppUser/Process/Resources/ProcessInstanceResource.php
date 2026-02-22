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
        public ?string $last_api_update,
        public string $status_label,
    ) {}

    public static function fromModel(Process $process, string $organizationId): self
    {
        $isActive = false;

        if ($process->relationLoaded('organizations')) {
            $organization = $process->organizations->firstWhere('id', $organizationId);
            if ($organization && $organization->pivot) {
                $isActive = (bool) $organization->pivot->is_active;
            }
        }

        $status = OrganizationProcessStatus::fromBoolean($isActive);

        return new self(
            id: $process->id,
            court: StrParseHelper::toTitleCase($process->court) ?? '',
            actions_count: $process->actions_count ?? 0,
            last_api_update: $process->last_api_update
                ? DateFormatHelper::formatDateTime($process->last_api_update)
                : null,
            status_label: $status->getLabel(),
        );
    }
}

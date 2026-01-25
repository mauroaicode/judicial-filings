<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Resources;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Models\Process;

class AdminProcessIndexResource extends Resource
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
        public ?string $organization,
        public ?int $organizations_count = null,
        public ?int $plaintiffs_count = null,
        public ?int $defendants_count = null,
    ) {}

    public static function fromModel(Process $process, int $index = 0): self
    {
        [$createdAt, $status] = self::getEarliestRegistrationDateAndStatus($process);
        [$organization, $organizationsCount] = self::getOrganizationInfo($process);
        [$plaintiff, $plaintiffsCount] = self::getPlaintiffInfo($process);
        [$defendant, $defendantsCount] = self::getDefendantInfo($process);

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
            organization: $organization,
            organizations_count: $organizationsCount,
            plaintiffs_count: $plaintiffsCount > 1 ? $plaintiffsCount : null,
            defendants_count: $defendantsCount > 1 ? $defendantsCount : null,
        );
    }

    /**
     * Get the earliest registration date and status from organizations.
     *
     * @return array{0: Carbon, 1: OrganizationProcessStatus}
     */
    private static function getEarliestRegistrationDateAndStatus(Process $process): array
    {
        $createdAt = $process->created_at;
        $isActive = false;

        if (! $process->relationLoaded('organizations')) {
            return [$createdAt, OrganizationProcessStatus::fromBoolean($isActive)];
        }

        $organizations = $process->organizations;
        if ($organizations->isEmpty()) {
            return [$createdAt, OrganizationProcessStatus::fromBoolean($isActive)];
        }

        $earliestOrg = $organizations->sortBy(function ($org) {
            $pivotCreatedAt = $org->pivot->created_at ?? null;

            return $pivotCreatedAt ? $pivotCreatedAt->timestamp : PHP_INT_MAX;
        })->first();

        if ($earliestOrg && $earliestOrg->pivot) {
            $pivotCreatedAt = $earliestOrg->pivot->created_at;
            $createdAt = $pivotCreatedAt ?? $process->created_at;
            $isActive = (bool) $earliestOrg->pivot->is_active;
        }

        return [$createdAt, OrganizationProcessStatus::fromBoolean($isActive)];
    }

    /**
     * Get organization information (name and count).
     *
     * @return array{0: string|null, 1: int|null}
     */
    private static function getOrganizationInfo(Process $process): array
    {
        if (! $process->relationLoaded('organizations')) {
            return [null, null];
        }

        $organizations = $process->organizations;
        $organizationsCount = $organizations->count();

        if ($organizationsCount === 0) {
            return [null, null];
        }

        $firstOrganization = $organizations->first();
        $organizationName = StrParseHelper::toTitleCase($firstOrganization->name) ?? $firstOrganization->name;

        $organization = $organizationsCount > 1
            ? $organizationName.' (+'.($organizationsCount - 1).')'
            : $organizationName;

        return [$organization, $organizationsCount];
    }

    /**
     * Get plaintiff information (name and count).
     *
     * @return array{0: string|null, 1: int|null}
     */
    private static function getPlaintiffInfo(Process $process): array
    {
        if (! $process->relationLoaded('subjects')) {
            return [null, null];
        }

        $plaintiffs = $process->subjects->where('subject_type', 'Demandante');
        $plaintiffsCount = $plaintiffs->count();

        if ($plaintiffs->isEmpty()) {
            return [null, null];
        }

        $firstPlaintiff = $plaintiffs->first();
        $plaintiffName = StrParseHelper::toTitleCase($firstPlaintiff->name_or_business_name) ?? '';

        $plaintiff = self::formatSubjectName($plaintiffName, $plaintiffsCount);

        return [$plaintiff, $plaintiffsCount];
    }

    /**
     * Get defendant information (name and count).
     *
     * @return array{0: string|null, 1: int|null}
     */
    private static function getDefendantInfo(Process $process): array
    {
        if (! $process->relationLoaded('subjects')) {
            return [null, null];
        }

        $defendants = $process->subjects->where('subject_type', 'Demandado');
        $defendantsCount = $defendants->count();

        if ($defendants->isEmpty()) {
            return [null, null];
        }

        $firstDefendant = $defendants->first();
        $defendantName = StrParseHelper::toTitleCase($firstDefendant->name_or_business_name) ?? '';

        $defendant = self::formatSubjectName($defendantName, $defendantsCount);

        return [$defendant, $defendantsCount];
    }

    /**
     * Format subject name with count indicator if there is multiple.
     */
    private static function formatSubjectName(string $name, int $count): string
    {
        return $count > 1 ? $name.' (+'.($count - 1).')' : $name;
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Resources;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Domain\Process\Models\Process;

class AdminProcessIndexResource extends Resource
{
    public function __construct(
        public int $index,
        public string $id,
        public string $process_number,
        public string $court,
        public ?string $speaker,
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
        public ?string $organization,
        public ?int $organizations_count = null,
        public ?int $plaintiffs_count = null,
        public ?int $defendants_count = null,
        /** @var list<string> Full list of organization names for tooltip */
        public array $organizations = [],
        /** @var list<string> Full list of plaintiff names for tooltip */
        public array $plaintiffs = [],
        /** @var list<string> Full list of defendant names for tooltip */
        public array $defendants = [],
    ) {}

    public static function fromModel(Process $process, int $index = 0): self
    {
        [$createdAt, $statusLabel] = self::getEarliestRegistrationDateAndJudicialStatusLabel($process);
        [$organization, $organizationsCount, $organizationsList] = self::getOrganizationInfo($process);
        [$plaintiff, $plaintiffsCount, $plaintiffsList] = self::getPlaintiffInfo($process);
        [$defendant, $defendantsCount, $defendantsList] = self::getDefendantInfo($process);

        return new self(
            index: $index,
            id: $process->id,
            process_number: $process->process_number,
            court: StrParseHelper::toTitleCase($process->court) ?? '',
            speaker: $process->speaker ? StrParseHelper::toTitleCase($process->speaker) : null,
            process_class: StrParseHelper::toTitleCase($process->process_class) ?? '',
            subclass_process: $process->subclass_process ? StrParseHelper::toTitleCase($process->subclass_process) : null,
            process_date: DateFormatHelper::formatDate($process->process_date),
            last_activity_date: $process->last_activity_date ? DateFormatHelper::formatDate($process->last_activity_date) : ($process->last_api_update ? DateFormatHelper::formatDateTime($process->last_api_update) : null),
            is_private: $process->is_private,
            has_multiple_instances: $process->has_multiple_instances,
            status_label: $statusLabel,
            created_at: DateFormatHelper::formatDate($createdAt),
            term_start_date: '-',
            term_end_date: '-',
            plaintiff: $plaintiff,
            defendant: $defendant,
            organization: $organization,
            organizations_count: $organizationsCount,
            plaintiffs_count: $plaintiffsCount,
            defendants_count: $defendantsCount,
            organizations: $organizationsList,
            plaintiffs: $plaintiffsList,
            defendants: $defendantsList,
        );
    }

    /**
     * Earliest org registration date from pivot; status label from `processes.status`.
     *
     * @return array{0: Carbon, 1: string}
     */
    private static function getEarliestRegistrationDateAndJudicialStatusLabel(Process $process): array
    {
        $createdAt = $process->created_at;

        if (! $process->relationLoaded('organizations')) {
            return [$createdAt, self::judicialStatusLabel($process)];
        }

        $organizations = $process->organizations;
        if ($organizations->isEmpty()) {
            return [$createdAt, self::judicialStatusLabel($process)];
        }

        $earliestOrg = $organizations->sortBy(function ($org) {
            $pivotCreatedAt = $org->pivot->created_at ?? null;

            return $pivotCreatedAt ? $pivotCreatedAt->timestamp : PHP_INT_MAX;
        })->first();

        if ($earliestOrg && $earliestOrg->pivot) {
            $pivotCreatedAt = $earliestOrg->pivot->created_at;
            $createdAt = $pivotCreatedAt ?? $process->created_at;
        }

        return [$createdAt, self::judicialStatusLabel($process)];
    }

    private static function judicialStatusLabel(Process $process): string
    {
        $raw = (string) ($process->status ?? '');

        return match ($raw) {
            'activo', 'active' => (string) __('enums.process_status.active'),
            'inactivo', 'inactive' => (string) __('enums.process_status.inactive'),
            'pending' => (string) __('enums.process_status.pending'),
            'closed', 'cerrado' => (string) __('enums.process_status.closed'),
            default => $raw !== '' ? $raw : '-',
        };
    }

    /**
     * Get organization information (summary, count and full list for tooltip).
     *
     * @return array{0: string|null, 1: int|null, 2: list<string>}
     */
    private static function getOrganizationInfo(Process $process): array
    {
        if (! $process->relationLoaded('organizations')) {
            return [null, null, []];
        }

        $organizations = $process->organizations;
        $organizationsCount = $organizations->count();

        if ($organizationsCount === 0) {
            return [null, null, []];
        }

        $names = $organizations->map(fn ($org) => StrParseHelper::toTitleCase($org->name) ?? $org->name)->values()->all();
        $firstOrganizationName = $names[0] ?? '';
        $organization = $organizationsCount > 1
            ? $firstOrganizationName.' (+'.($organizationsCount - 1).')'
            : $firstOrganizationName;

        return [$organization, $organizationsCount, $names];
    }

    /**
     * Get plaintiff information (summary, count and full list for tooltip).
     *
     * @return array{0: string|null, 1: int, 2: list<string>}
     */
    private static function getPlaintiffInfo(Process $process): array
    {
        if (! $process->relationLoaded('subjects')) {
            return [null, 0, []];
        }

        $plaintiffs = $process->subjects->where('subject_type', 'Demandante');
        $plaintiffsCount = $plaintiffs->count();

        if ($plaintiffs->isEmpty()) {
            return [null, 0, []];
        }

        $names = $plaintiffs->map(fn ($s): string => StrParseHelper::toTitleCase($s->name_or_business_name) ?? '')->values()->all();
        $firstPlaintiffName = $names[0] ?? '';
        $plaintiff = self::formatSubjectName($firstPlaintiffName, $plaintiffsCount);

        return [$plaintiff, $plaintiffsCount, $names];
    }

    /**
     * Get defendant information (summary, count and full list for tooltip).
     *
     * @return array{0: string|null, 1: int, 2: list<string>}
     */
    private static function getDefendantInfo(Process $process): array
    {
        if (! $process->relationLoaded('subjects')) {
            return [null, 0, []];
        }

        $defendants = $process->subjects->where('subject_type', 'Demandado');
        $defendantsCount = $defendants->count();

        if ($defendants->isEmpty()) {
            return [null, 0, []];
        }

        $names = $defendants->map(fn ($s): string => StrParseHelper::toTitleCase($s->name_or_business_name) ?? '')->values()->all();
        $firstDefendantName = $names[0] ?? '';
        $defendant = self::formatSubjectName($firstDefendantName, $defendantsCount);

        return [$defendant, $defendantsCount, $names];
    }

    /**
     * Format subject name with count indicator if there is multiple.
     */
    private static function formatSubjectName(string $name, int $count): string
    {
        return $count > 1 ? $name.' (+'.($count - 1).')' : $name;
    }
}

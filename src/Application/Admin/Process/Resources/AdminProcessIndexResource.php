<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Resources;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\ProcessSubjectSummaryHelper;
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
        public bool $is_manual_sync,
        public ?string $data_source_slug,
        public ?string $data_source_name,
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
        public ?int $others_count = null,
        public ?int $subjects_count = null,
        /** @var list<string> Full list of organization names for tooltip */
        public array $organizations = [],
        /** @var list<string> Full list of plaintiff names for tooltip */
        public array $plaintiffs = [],
        /** @var list<string> Full list of defendant names for tooltip */
        public array $defendants = [],
        public ?string $other_subject = null,
        /** @var list<string> Full list of other subject names for tooltip */
        public array $others = [],
    ) {}

    public static function fromModel(Process $process, int $index = 0): self
    {
        [$createdAt, $statusLabel] = self::getEarliestRegistrationDateAndJudicialStatusLabel($process);
        [$organization, $organizationsCount, $organizationsList] = self::getOrganizationInfo($process);
        $subjectSummary = self::getSubjectSummary($process);

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
            is_manual_sync: $process->is_manual_sync,
            data_source_slug: $process->relationLoaded('processDataSource') ? $process->processDataSource?->slug : null,
            data_source_name: $process->relationLoaded('processDataSource') ? $process->processDataSource?->name : null,
            status_label: $statusLabel,
            created_at: DateFormatHelper::formatDate($createdAt),
            term_start_date: '-',
            term_end_date: '-',
            plaintiff: $subjectSummary['plaintiff'],
            defendant: $subjectSummary['defendant'],
            organization: $organization,
            organizations_count: $organizationsCount,
            plaintiffs_count: $subjectSummary['plaintiffs_count'],
            defendants_count: $subjectSummary['defendants_count'],
            others_count: $subjectSummary['others_count'],
            subjects_count: $subjectSummary['subjects_count'],
            organizations: $organizationsList,
            plaintiffs: $subjectSummary['plaintiffs'],
            defendants: $subjectSummary['defendants'],
            other_subject: $subjectSummary['other_subject'],
            others: $subjectSummary['others'],
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
     * @return array{
     *     plaintiffs_count: int,
     *     defendants_count: int,
     *     others_count: int,
     *     subjects_count: int,
     *     plaintiff: string|null,
     *     defendant: string|null,
     *     other_subject: string|null,
     *     plaintiffs: list<string>,
     *     defendants: list<string>,
     *     others: list<string>,
     * }
     */
    private static function getSubjectSummary(Process $process): array
    {
        if (! $process->relationLoaded('subjects')) {
            return ProcessSubjectSummaryHelper::summarize(collect());
        }

        return ProcessSubjectSummaryHelper::summarize($process->subjects);
    }
}

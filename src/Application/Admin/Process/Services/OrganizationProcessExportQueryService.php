<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Src\Application\Admin\Process\Data\OrganizationProcessExportData;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;

readonly class OrganizationProcessExportQueryService
{
    public function __construct(
        private OrganizationProcessExportPivotFilter $pivotFilter,
    ) {}

    /**
     * Yield Excel rows (without the # index) for the given organization/filters.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function yieldRows(string $organizationId, OrganizationProcessExportData $filters): Generator
    {
        $chunkSize = max(1, (int) config('process-export.chunk_size', 100));
        $needsSubjects = $filters->needsSubjects();

        $processNumbers = $this->baseQuery($organizationId, $filters)
            ->select('processes.process_number')
            ->distinct()
            ->orderBy('processes.process_number')
            ->pluck('process_number');

        foreach ($processNumbers->chunk($chunkSize) as $chunk) {
            /** @var Collection<int, string> $chunk */
            $processes = Process::query()
                ->whereIn('process_number', $chunk->all())
                ->whereHas('organizations', function (Builder $query) use ($organizationId, $filters): void {
                    $this->pivotFilter->apply($query, $organizationId, $filters);
                })
                ->with([
                    'organizations' => fn ($query) => $query->where('organizations.id', $organizationId),
                    ...($needsSubjects ? ['subjects'] : []),
                ])
                ->get()
                ->groupBy('process_number');

            foreach ($chunk as $processNumber) {
                $instances = $processes->get($processNumber, collect());
                if ($instances->isEmpty()) {
                    continue;
                }

                yield $this->mapRadicadoRow($instances, $filters);
            }
        }
    }

    public function countRows(string $organizationId, OrganizationProcessExportData $filters): int
    {
        return (int) $this->baseQuery($organizationId, $filters)
            ->selectRaw('COUNT(DISTINCT processes.process_number) as aggregate')
            ->value('aggregate');
    }

    /**
     * @return Builder<Process>
     */
    public function baseQuery(string $organizationId, OrganizationProcessExportData $filters): Builder
    {
        return Process::query()
            ->whereHas('organizations', function (Builder $query) use ($organizationId, $filters): void {
                $this->pivotFilter->apply($query, $organizationId, $filters);
            });
    }

    /**
     * @param  Collection<int, Process>  $instances
     * @return array<string, mixed>
     */
    private function mapRadicadoRow(Collection $instances, OrganizationProcessExportData $filters): array
    {
        $court = $instances
            ->map(fn (Process $process): string => mb_strtoupper(trim((string) $process->court)))
            ->filter()
            ->sort()
            ->last();

        $processClass = $instances
            ->map(fn (Process $process): string => mb_strtoupper(trim((string) $process->process_class)))
            ->filter()
            ->sort()
            ->last();

        $lawyerRole = $instances
            ->map(function (Process $process): ?string {
                $role = $process->organizations->first()?->pivot?->lawyer_role;

                if ($role instanceof ProcessLawyerRole) {
                    return $role->value;
                }

                return is_string($role) ? $role : null;
            })
            ->filter()
            ->sort()
            ->last();

        $row = [
            'Número de radicado' => (string) $instances->first()?->process_number,
            'Despacho' => $court ?: null,
            'Clase de proceso' => $processClass ?: null,
            'Rol abogado' => $this->formatLawyerRole($lawyerRole),
            'Estado' => $this->formatStatus($instances),
        ];

        if ($filters->include_plaintiffs || $filters->include_defendants || $filters->include_other_subjects) {
            $subjects = $instances
                ->flatMap(fn (Process $process): Collection => $process->subjects)
                ->unique('id')
                ->values();

            if ($filters->include_plaintiffs) {
                $row['Demandante'] = $this->formatSubjectGroup($subjects, ProcessSubject::TYPE_PLAINTIFF);
            }

            if ($filters->include_defendants) {
                $row['Demandado'] = $this->formatSubjectGroup($subjects, ProcessSubject::TYPE_DEFENDANT);
            }

            if ($filters->include_other_subjects) {
                $row['Otros sujetos'] = $this->formatOtherSubjects($subjects);
            }
        }

        return $row;
    }

    private function formatLawyerRole(?string $role): ?string
    {
        return match ($role) {
            ProcessLawyerRole::PLAINTIFF->value => 'Demandante',
            ProcessLawyerRole::DEFENDANT->value => 'Demandado',
            default => $role,
        };
    }

    /**
     * @param  Collection<int, Process>  $instances
     */
    private function formatStatus(Collection $instances): ?string
    {
        $status = $instances
            ->map(function (Process $process): ?string {
                $value = $process->organizations->first()?->pivot?->status;

                if ($value instanceof OrganizationProcessStatus) {
                    return $value->value;
                }

                return is_string($value) ? $value : null;
            })
            ->filter()
            ->sort()
            ->last();

        return match ($status) {
            OrganizationProcessStatus::ACTIVE->value => 'Activo',
            OrganizationProcessStatus::INACTIVE->value => 'Inactivo',
            OrganizationProcessStatus::SUSPENDED->value => 'Suspendido',
            default => $status,
        };
    }

    /**
     * @param  Collection<int, ProcessSubject>  $subjects
     */
    private function formatSubjectGroup(Collection $subjects, string $type): ?string
    {
        $names = $subjects
            ->filter(fn (ProcessSubject $subject): bool => mb_strtoupper(trim($subject->subject_type)) === mb_strtoupper($type))
            ->map(fn (ProcessSubject $subject): string => mb_strtoupper(trim($subject->name_or_business_name)))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return $this->formatNameList($names);
    }

    /**
     * @param  Collection<int, ProcessSubject>  $subjects
     */
    private function formatOtherSubjects(Collection $subjects): ?string
    {
        $excluded = [
            mb_strtoupper(ProcessSubject::TYPE_PLAINTIFF),
            mb_strtoupper(ProcessSubject::TYPE_DEFENDANT),
            '',
        ];

        $names = $subjects
            ->filter(function (ProcessSubject $subject) use ($excluded): bool {
                $type = mb_strtoupper(trim((string) $subject->subject_type));

                return ! in_array($type, $excluded, true);
            })
            ->map(fn (ProcessSubject $subject): string => mb_strtoupper(trim($subject->name_or_business_name)))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return $this->formatNameList($names);
    }

    /**
     * @param  Collection<int, string>  $names
     */
    private function formatNameList(Collection $names): ?string
    {
        $count = $names->count();

        if ($count === 0) {
            return null;
        }

        $first = $names->first();

        if ($count === 1) {
            return $first;
        }

        return $first.' (+'.($count - 1).')';
    }
}

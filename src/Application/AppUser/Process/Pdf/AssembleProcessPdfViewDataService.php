<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Pdf;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Src\Application\AppUser\Process\Services\ProcessDetailService;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\ProcessNumberFormatHelper;
use Src\Application\Shared\Helpers\ProcessSubjectIdentityHelper;
use Src\Application\Shared\Helpers\ProcessSubjectSummaryHelper;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Application\Shared\Pdf\PdfLogoDataUri;
use Src\Application\Shared\Process\Resources\ProcessDetailResource;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;
use Src\Domain\Shared\Enums\SeverityColor;

class AssembleProcessPdfViewDataService
{
    public function __construct(
        private readonly ProcessDetailService $processDetailService,
        private readonly CollectProcessPdfActionsService $collectProcessPdfActionsService,
        private readonly CollectProcessPdfTimelineService $collectProcessPdfTimelineService,
        private readonly CollectProcessPdfTasksService $collectProcessPdfTasksService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(string $processId, string $organizationId): array
    {
        $process = $this->processDetailService->handle($processId, $organizationId);

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $detail = ProcessDetailResource::fromModel($process, $organizationId)->toArray();
        $processNumberFormatted = ProcessNumberFormatHelper::display($process->process_number, $process->process_number);
        $subjects = $this->buildSubjects($process);
        $instancesCount = $this->countSiblingInstances($process, $organizationId);
        $alertLevel = is_string($detail['alert_level'] ?? null) ? $detail['alert_level'] : null;

        return [
            'logoDataUri' => PdfLogoDataUri::resolve(),
            'generatedAt' => DateFormatHelper::formatDateWithTime(now()),
            'filename' => $this->buildFilename($processNumberFormatted),
            'processNumberFormatted' => $processNumberFormatted,
            'detail' => $detail,
            'alertLevelLabel' => SeverityColor::tryFrom((string) $alertLevel)?->getLabel(),
            'subjects' => $subjects,
            'instances' => [
                'has_multiple' => $instancesCount > 1 || (bool) $process->has_multiple_instances,
                'count' => $instancesCount,
                'current_court' => $detail['court'] ?? $process->court,
            ],
            'tasks' => $this->collectProcessPdfTasksService->handle($process, $organizationId),
            'timeline' => $this->collectProcessPdfTimelineService->handle($process, $organizationId),
            'actions' => $this->collectProcessPdfActionsService->handle($process),
        ];
    }

    /**
     * @return array{plaintiffs: list<array<string, mixed>>, defendants: list<array<string, mixed>>, others: list<array<string, mixed>>}
     */
    private function buildSubjects(Process $process): array
    {
        $unique = ProcessSubjectIdentityHelper::deduplicate($process->subjects);
        $grouped = ProcessSubjectSummaryHelper::group($unique);

        return [
            'plaintiffs' => $this->mapSubjects($grouped['plaintiffs']),
            'defendants' => $this->mapSubjects($grouped['defendants']),
            'others' => $this->mapSubjects($grouped['others']),
        ];
    }

    /**
     * @param  Collection<int, ProcessSubject>  $subjects
     * @return list<array{name: string, identification: string|null, type: string, is_cited: bool}>
     */
    private function mapSubjects(Collection $subjects): array
    {
        return $subjects
            ->map(fn (ProcessSubject $subject): array => [
                'name' => StrParseHelper::toTitleCase($subject->name_or_business_name) ?? $subject->name_or_business_name,
                'identification' => $subject->identification,
                'type' => $subject->subject_type,
                'is_cited' => (bool) $subject->is_cited,
            ])
            ->values()
            ->all();
    }

    private function countSiblingInstances(Process $process, string $organizationId): int
    {
        return Process::query()
            ->where('process_number', $process->process_number)
            ->whereOrganization($organizationId)
            ->count();
    }

    private function buildFilename(string $processNumberFormatted): string
    {
        $slug = Str::slug($processNumberFormatted) ?: 'proceso';

        return "proceso-{$slug}.pdf";
    }
}

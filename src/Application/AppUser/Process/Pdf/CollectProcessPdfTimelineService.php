<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Pdf;

use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Process\Timeline\Presenters\ProcessTimelineEventPresenter;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessTimelineEvent;

class CollectProcessPdfTimelineService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(Process $process, string $organizationId): array
    {
        return ProcessTimelineEvent::query()
            ->forProcess($process->id)
            ->visibleToOrganization($organizationId)
            ->latestFirst()
            ->get()
            ->map(function (ProcessTimelineEvent $event): array {
                $display = ProcessTimelineEventPresenter::for($event);

                return [
                    'occurred_at' => DateFormatHelper::formatDateTimeWithDayOfWeek($event->occurred_at),
                    'title' => $display['title'] ?? '',
                    'summary' => $display['summary'] ?? null,
                    'reason' => $display['reason'] ?? null,
                    'source' => $display['source'] ?? null,
                    'task_type' => $display['task_type'] ?? null,
                    'task_status' => $display['task_status'] ?? null,
                ];
            })
            ->all();
    }
}

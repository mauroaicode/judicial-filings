<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Pdf;

use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Services\GroupProcessActionsService;

class CollectProcessPdfActionsService
{
    public function __construct(
        private readonly GroupProcessActionsService $groupProcessActionsService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(Process $process): array
    {
        $rows = ProcessAction::query()
            ->whereProcess($process->id)
            ->orderedByActionDate()
            ->orderedByRegistrationDate()
            ->orderByDesc('cons_action')
            ->get()
            ->map(fn (ProcessAction $action): array => $this->mapAction($action))
            ->values();

        if ($rows->isEmpty()) {
            return [];
        }

        return $this->groupProcessActionsService
            ->handleForConsolidado($rows)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAction(ProcessAction $action): array
    {
        return [
            'id' => $action->id,
            'process_action_id' => $action->id,
            'cons_action' => $action->cons_action,
            'action' => $action->action,
            'action_text' => $action->action,
            'annotation' => $action->annotation ?: '---',
            'action_date' => DateFormatHelper::formatDate($action->action_date),
            'registration_date' => DateFormatHelper::formatDate($action->registration_date),
            'term_start_date' => $action->start_date ? DateFormatHelper::formatDate($action->start_date) : null,
            'term_end_date' => $action->end_date ? DateFormatHelper::formatDate($action->end_date) : null,
            'removed_from_judicial_branch' => $action->removed_from_judicial_branch_at !== null,
        ];
    }
}

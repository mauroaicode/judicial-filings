<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Excel;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\ProcessNumberFormatHelper;
use Src\Application\Shared\Process\Data\ProcessActionFilterData;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

/**
 * Streams actuación rows for a single process using the same filters as the detail table.
 */
class CollectProcessActionsExcelRowsService
{
    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function yieldRows(Process $process, ProcessActionFilterData $filters): Generator
    {
        foreach ($this->baseQuery($process, $filters)->cursor() as $action) {
            /** @var ProcessAction $action */
            yield $this->mapRow($process, $action);
        }
    }

    /**
     * @return Builder<ProcessAction>
     */
    private function baseQuery(Process $process, ProcessActionFilterData $filters): Builder
    {
        return ProcessAction::query()
            ->whereProcess($process->id)
            ->filters($filters)
            ->orderedByActionDate()
            ->orderedByRegistrationDate()
            ->orderByDesc('cons_action')
            ->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(Process $process, ProcessAction $action): array
    {
        return [
            'Número de radicado' => ProcessNumberFormatHelper::display(
                $process->process_number,
                $process->process_number,
            ),
            'Despacho' => $process->court,
            'Fecha actuación' => DateFormatHelper::formatIsoDate($action->action_date),
            'Actuación' => $action->action,
            'Anotación' => $action->annotation,
            'Fecha registro' => DateFormatHelper::formatIsoDate($action->registration_date),
            'Fecha inicio término' => DateFormatHelper::formatIsoDate($action->start_date),
            'Fecha fin término' => DateFormatHelper::formatIsoDate($action->end_date),
            'Consecutivo' => $action->cons_action,
            'Retirada en Rama' => $action->removed_from_judicial_branch_at !== null
                ? __('process.process_pdf_yes')
                : __('process.process_pdf_no'),
        ];
    }
}

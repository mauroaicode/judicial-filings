<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Support;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Src\Application\Admin\Process\Data\OrganizationProcessExportData;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\Process\Models\ProcessAction;

/**
 * Streams actuación rows for Excel without loading the full result set into memory.
 */
readonly class OrganizationProcessExportActionQueryService
{
    public function __construct(
        private OrganizationProcessExportQueryService $processQueryService,
    ) {}

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function yieldRows(string $organizationId, OrganizationProcessExportData $filters): Generator
    {
        if (! $filters->include_actions) {
            return;
        }

        foreach ($this->baseQuery($organizationId, $filters)->cursor() as $action) {
            /** @var ProcessAction $action */
            yield $this->mapRow($action);
        }
    }

    public function countRows(string $organizationId, OrganizationProcessExportData $filters): int
    {
        if (! $filters->include_actions) {
            return 0;
        }

        return (int) $this->baseQuery($organizationId, $filters)->count();
    }

    /**
     * @return Builder<ProcessAction>
     */
    private function baseQuery(string $organizationId, OrganizationProcessExportData $filters): Builder
    {
        $processIds = $this->processQueryService
            ->baseQuery($organizationId, $filters)
            ->select('processes.id');

        $query = ProcessAction::query()
            ->select([
                'process_actions.id',
                'process_actions.process_id',
                'process_actions.cons_action',
                'process_actions.action_date',
                'process_actions.action',
                'process_actions.annotation',
                'process_actions.start_date',
                'process_actions.end_date',
                'process_actions.registration_date',
                'processes.process_number',
                'processes.court',
            ])
            ->join('processes', 'processes.id', '=', 'process_actions.process_id')
            ->whereIn('process_actions.process_id', $processIds)
            ->orderBy('processes.process_number')
            ->oldest('process_actions.action_date')
            ->orderBy('process_actions.cons_action')
            ->orderBy('process_actions.id');

        if ($filters->actions_from) {
            $query->whereDate(
                'process_actions.action_date',
                '>=',
                Date::parse($filters->actions_from)->toDateString()
            );
        }

        if ($filters->actions_to) {
            $query->whereDate(
                'process_actions.action_date',
                '<=',
                Date::parse($filters->actions_to)->toDateString()
            );
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(ProcessAction $action): array
    {
        $court = mb_strtoupper(trim((string) $action->getAttribute('court')));

        return [
            'Número de radicado' => (string) $action->getAttribute('process_number'),
            'Despacho' => $court !== '' ? $court : null,
            'Fecha actuación' => DateFormatHelper::formatIsoDate($action->action_date),
            'Actuación' => $action->action,
            'Anotación' => $action->annotation,
            'Fecha registro' => DateFormatHelper::formatIsoDate($action->registration_date),
            'Fecha inicio' => DateFormatHelper::formatIsoDate($action->start_date),
            'Fecha fin' => DateFormatHelper::formatIsoDate($action->end_date),
            'Consecutivo' => $action->cons_action,
        ];
    }
}

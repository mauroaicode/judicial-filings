<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Collection;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Services\GroupProcessActionsService;

/**
 * Consolidado pairing for Actuaciones recientes, admin pending, and admin history.
 *
 * Loads same-day Fijaciones already on the process (even if they went out in an
 * earlier package) so leftover Autos are not shown unpaired.
 */
class AssembleConsolidadoDigestRowsService
{
    public function __construct(
        private readonly GroupProcessActionsService $groupProcessActionsService,
    ) {}

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $context = $this->sameDaySiblingRows($rows);

        return $this->groupProcessActionsService->handleForConsolidado(
            $rows,
            $context->isNotEmpty() ? $context : null,
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sameDaySiblingRows(Collection $rows): Collection
    {
        $existingIds = $rows
            ->map(fn (array $row): mixed => $row['process_action_id'] ?? $row['id'] ?? null)
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values();

        $processNumbers = $rows
            ->map(fn (array $row): string => (string) ($row['process_number'] ?? ''))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($processNumbers === []) {
            return collect();
        }

        $datesByProcess = [];
        foreach ($rows as $row) {
            $processNumber = (string) ($row['process_number'] ?? '');
            $day = $this->rowDayKey($row);
            if ($processNumber === '' || $day === null) {
                continue;
            }

            $datesByProcess[$processNumber][$day] = true;
        }

        if ($datesByProcess === []) {
            return collect();
        }

        $siblings = ProcessAction::query()
            ->with(['process:id,process_number'])
            ->whereHas('process', fn ($query) => $query->whereIn('process_number', $processNumbers))
            ->when($existingIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $existingIds->all()))
            ->get();

        return $siblings
            ->filter(function (ProcessAction $action) use ($datesByProcess): bool {
                $processNumber = (string) ($action->process->process_number ?? '');
                $day = $this->dayKey($action->registration_date)
                    ?? $this->dayKey($action->action_date);

                return $processNumber !== ''
                    && $day !== null
                    && isset($datesByProcess[$processNumber][$day]);
            })
            ->map(fn (ProcessAction $action): array => $this->toPairingRow($action))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function toPairingRow(ProcessAction $action): array
    {
        $processNumber = (string) ($action->process->process_number ?? '');

        return [
            'process_action_id' => $action->id,
            'process_number' => $processNumber,
            'action' => (string) $action->action,
            'action_text' => (string) $action->action,
            'annotation' => (string) ($action->annotation ?: ''),
            'cons_action' => $action->cons_action,
            'registration_date' => DateFormatHelper::formatDate($action->registration_date),
            'registration_date_iso' => DateFormatHelper::formatIsoDate($action->registration_date),
            'action_date' => DateFormatHelper::formatDate($action->action_date),
            'action_date_iso' => DateFormatHelper::formatIsoDate($action->action_date),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowDayKey(array $row): ?string
    {
        foreach (['registration_date_iso', 'action_date_iso', 'registration_date', 'action_date'] as $key) {
            $day = $this->dayKey($row[$key] ?? null);
            if ($day !== null) {
                return $day;
            }
        }

        return null;
    }

    private function dayKey(mixed $value): ?string
    {
        $parsed = DateFormatHelper::tryParseDate($value);

        return $parsed?->toDateString();
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Support\Facades\DB;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Services\FijacionEstadoActionSplitter;

/**
 * Repairs actuaciones already imported as a single combined title
 * ("Fijación Estado Auto …") by splitting them into two rows so
 * {@see \Src\Domain\Process\Services\GroupProcessActionsService} can pair them.
 */
final class SplitCombinedFijacionEstadoActionsService
{
    public function __construct(
        private readonly FijacionEstadoActionSplitter $splitter,
    ) {}

    /**
     * @param  list<string>|null  $processNumbers  Null = scan all candidates.
     * @return array{
     *     scanned: int,
     *     split: int,
     *     skipped_already_split: int,
     *     skipped_not_combined: int,
     *     items: list<array{process_number: string, process_action_id: string, from: string, estado: string, decision: string}>
     * }
     */
    public function handle(?array $processNumbers, bool $dryRun): array
    {
        $query = ProcessAction::query()
            ->with('process:id,process_number')
            ->where(function ($q): void {
                $q->where('action', 'like', 'Fijacion Estado %')
                    ->orWhere('action', 'like', 'Fijación Estado %')
                    ->orWhere('action', 'like', 'Notificacion Estado %')
                    ->orWhere('action', 'like', 'Notificación Estado %')
                    ->orWhere('action', 'like', 'Publicacion Estado %')
                    ->orWhere('action', 'like', 'Publicación Estado %');
            })
            ->orderBy('process_id')
            ->orderBy('cons_action');

        if ($processNumbers !== null && $processNumbers !== []) {
            $query->whereHas('process', function ($q) use ($processNumbers): void {
                $q->whereIn('process_number', $processNumbers);
            });
        }

        $candidates = $query->get();

        $scanned = 0;
        $split = 0;
        $skippedAlreadySplit = 0;
        $skippedNotCombined = 0;
        $items = [];

        $seed = $this->nextNegativeRegistrationSeed();

        foreach ($candidates as $action) {
            $scanned++;
            $parts = $this->splitter->split((string) $action->action);

            if (count($parts) < 2) {
                $skippedNotCombined++;

                continue;
            }

            [$estadoLabel, $decisionLabel] = $parts;

            if ($this->decisionAlreadyExists($action, $decisionLabel)) {
                $skippedAlreadySplit++;

                continue;
            }

            $processNumber = (string) ($action->process?->process_number ?? '');

            $items[] = [
                'process_number' => $processNumber,
                'process_action_id' => $action->id,
                'from' => (string) $action->action,
                'estado' => $estadoLabel,
                'decision' => $decisionLabel,
            ];

            if ($dryRun) {
                $split++;

                continue;
            }

            DB::transaction(function () use ($action, $estadoLabel, $decisionLabel, &$seed): void {
                $action->update(['action' => $estadoLabel]);

                $nextCons = (int) (ProcessAction::query()
                    ->where('process_id', $action->process_id)
                    ->max('cons_action') ?? 0) + 1;

                ProcessAction::query()->create([
                    'process_id' => $action->process_id,
                    'action_registration_id' => $seed,
                    'cons_action' => $nextCons,
                    'action_date' => $action->action_date,
                    'action' => $decisionLabel,
                    'annotation' => $action->annotation,
                    'start_date' => $action->start_date,
                    'end_date' => $action->end_date,
                    'registration_date' => $action->registration_date,
                ]);

                $seed--;
            });

            $split++;
        }

        return [
            'scanned' => $scanned,
            'split' => $split,
            'skipped_already_split' => $skippedAlreadySplit,
            'skipped_not_combined' => $skippedNotCombined,
            'items' => $items,
        ];
    }

    private function decisionAlreadyExists(ProcessAction $action, string $decisionLabel): bool
    {
        $query = ProcessAction::query()
            ->where('process_id', $action->process_id)
            ->where('action', $decisionLabel)
            ->where('id', '!=', $action->id);

        if ($action->registration_date !== null) {
            $query->whereDate('registration_date', $action->registration_date->format('Y-m-d'));
        }

        return $query->exists();
    }

    private function nextNegativeRegistrationSeed(): int
    {
        $minAct = ProcessAction::query()->where('action_registration_id', '<', 0)->min('action_registration_id');

        return $minAct === null ? -1 : (int) $minAct - 1;
    }
}

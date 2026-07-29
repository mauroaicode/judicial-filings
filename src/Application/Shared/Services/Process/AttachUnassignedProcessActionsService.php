<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\UnassignedProcessAction;

/**
 * Migrates orphan Excel actuaciones (stored by radicado) onto a newly created Process.
 *
 * Does NOT enqueue digest/consolidado notifications — historical backfill must not
 * trigger client alerts (same policy as process-creation imports).
 */
class AttachUnassignedProcessActionsService
{
    /**
     * @return int Number of actuaciones attached
     */
    public function handle(Process $process): int
    {
        $processNumber = (string) $process->process_number;
        if ($processNumber === '') {
            return 0;
        }

        $pending = UnassignedProcessAction::query()
            ->whereProcessNumber($processNumber)
            ->whereUnassigned()
            ->orderedByRegistrationDate()
            ->get();

        if ($pending->isEmpty()) {
            return 0;
        }

        $attached = 0;

        DB::transaction(function () use ($process, $pending, &$attached): void {
            $nextCons = (int) (ProcessAction::query()
                ->where('process_id', $process->id)
                ->max('cons_action') ?? 0);

            $minAct = ProcessAction::query()
                ->where('action_registration_id', '<', 0)
                ->min('action_registration_id');
            $actionRegistrationSeed = $minAct === null ? -1 : (int) $minAct - 1;

            foreach ($pending as $orphan) {
                if ($this->actionAlreadyExists($process->id, $orphan)) {
                    $orphan->delete();

                    continue;
                }

                $registrationDate = $orphan->registration_date?->format('Y-m-d')
                    ?? $orphan->start_date?->format('Y-m-d')
                    ?? now()->format('Y-m-d');

                $nextCons++;

                ProcessAction::query()->create([
                    'process_id' => $process->id,
                    'action_registration_id' => $actionRegistrationSeed,
                    'cons_action' => max(1, $nextCons),
                    'action_date' => $registrationDate,
                    'action' => $orphan->action,
                    'annotation' => $orphan->annotation,
                    'start_date' => $orphan->start_date?->format('Y-m-d'),
                    'end_date' => $orphan->end_date?->format('Y-m-d'),
                    'registration_date' => $registrationDate,
                ]);

                $actionRegistrationSeed--;
                $attached++;

                $orphan->delete();
            }

            $this->refreshProcessActivityBoundaries($process);
        });

        if ($attached > 0) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->info('AttachUnassignedProcessActionsService: historical actuaciones attached', [
                    'process_id' => $process->id,
                    'process_number' => $process->process_number,
                    'attached' => $attached,
                ]);
        }

        return $attached;
    }

    private function actionAlreadyExists(string $processId, UnassignedProcessAction $orphan): bool
    {
        $registrationDate = $orphan->registration_date?->format('Y-m-d')
            ?? $orphan->start_date?->format('Y-m-d')
            ?? now()->format('Y-m-d');

        $query = ProcessAction::query()
            ->where('process_id', $processId)
            ->whereDate('registration_date', $registrationDate)
            ->where('action', $orphan->action);

        if ($orphan->annotation === null || $orphan->annotation === '') {
            $query->whereNull('annotation');
        } else {
            $query->where('annotation', $orphan->annotation);
        }

        return $query->exists();
    }

    private function refreshProcessActivityBoundaries(Process $process): void
    {
        $process->refresh();

        $dates = ProcessAction::query()
            ->where('process_id', $process->id)
            ->get(['registration_date', 'action_date', 'end_date']);

        if ($dates->isEmpty()) {
            return;
        }

        $minReg = null;
        $maxActivity = null;

        foreach ($dates as $row) {
            foreach ([$row->registration_date, $row->action_date, $row->end_date] as $date) {
                if ($date === null) {
                    continue;
                }
                $formatted = $date->format('Y-m-d');
                if ($minReg === null || $formatted < $minReg) {
                    $minReg = $formatted;
                }
                if ($maxActivity === null || $formatted > $maxActivity) {
                    $maxActivity = $formatted;
                }
            }
        }

        $updates = [];

        if ($minReg !== null) {
            $currentPd = $process->process_date->format('Y-m-d');
            if ($minReg < $currentPd) {
                $updates['process_date'] = $minReg;
            }
        }

        if ($maxActivity !== null) {
            $currentLa = $process->last_activity_date?->format('Y-m-d');
            if ($currentLa === null || $maxActivity > $currentLa) {
                $updates['last_activity_date'] = $maxActivity;
            }
        }

        if ($updates !== []) {
            $process->update($updates);
        }
    }
}
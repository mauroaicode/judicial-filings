<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Services;

use Illuminate\Support\Facades\DB;
use Src\Application\Shared\Process\Support\SpeakerChangeAnnotationParser;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

class BackfillSpeakerFromProcessActionsService
{
    public function __construct(
        private readonly ApplySpeakerChangeFromProcessActionService $applySpeakerChangeFromProcessActionService,
    ) {}

    /**
     * Align processes.speaker with the latest "Cambio de ponente" action per process.
     *
     * @return array{updated: int, skipped: int, scanned: int}
     */
    public function handle(bool $dryRun = false, int $chunkSize = 200): array
    {
        $counts = ['updated' => 0, 'skipped' => 0, 'scanned' => 0];
        $chunkSize = max(1, $chunkSize);

        ProcessAction::query()
            ->select('process_id')
            ->where(function (\Illuminate\Contracts\Database\Query\Builder $query): void {
                $query->where('action', 'like', '%Cambio de ponente%')
                    ->orWhere('annotation', 'like', '%Ponente nuevo:%');
            })
            ->distinct()
            ->orderBy('process_id')
            ->chunk($chunkSize, function ($rows) use (&$counts, $dryRun): void {
                foreach ($rows as $row) {
                    $counts['scanned']++;
                    $processId = (string) $row->process_id;

                    $process = Process::query()->whereKey($processId)->first();
                    if (! $process instanceof Process) {
                        $counts['skipped']++;

                        continue;
                    }

                    $latestAction = ProcessAction::query()
                        ->where('process_id', $processId)
                        ->where(function (\Illuminate\Contracts\Database\Query\Builder $query): void {
                            $query->where('action', 'like', '%Cambio de ponente%')
                                ->orWhere('annotation', 'like', '%Ponente nuevo:%');
                        })
                        ->latest('action_date')
                        ->latest('registration_date')
                        ->latest()
                        ->first();

                    if (! $latestAction instanceof ProcessAction) {
                        $counts['skipped']++;

                        continue;
                    }

                    if ($dryRun) {
                        $wouldUpdate = $this->wouldUpdate($process, $latestAction);
                        if ($wouldUpdate) {
                            $counts['updated']++;
                        } else {
                            $counts['skipped']++;
                        }

                        continue;
                    }

                    $updated = DB::transaction(fn (): bool => $this->applySpeakerChangeFromProcessActionService->handle(
                        $process,
                        $latestAction,
                        ProcessTimelineEventSource::BACKFILL,
                    ));

                    if ($updated) {
                        $counts['updated']++;
                    } else {
                        $counts['skipped']++;
                    }
                }
            });

        return $counts;
    }

    private function wouldUpdate(Process $process, ProcessAction $action): bool
    {
        $parsed = SpeakerChangeAnnotationParser::parse(
            $action->action,
            $action->annotation,
        );

        if ($parsed === null || $parsed['new'] === null) {
            return false;
        }

        return mb_strtolower(trim((string) ($process->speaker ?? ''))) !== mb_strtolower($parsed['new']);
    }
}

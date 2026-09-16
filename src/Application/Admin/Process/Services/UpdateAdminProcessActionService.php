<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Src\Application\Admin\Process\Data\UpdateAdminProcessActionData;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

readonly class UpdateAdminProcessActionService
{
    public function handle(string $processId, string $actionId, UpdateAdminProcessActionData $data): ProcessAction
    {
        $process = Process::query()->find($processId);

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $action = ProcessAction::query()
            ->where('id', $actionId)
            ->where('process_id', $processId)
            ->first();

        if (! $action instanceof ProcessAction) {
            abort(404, __('process.action_not_found'));
        }

        $updates = $data->toActionUpdates();

        $this->assertTermRange($action, $updates);

        DB::transaction(function () use ($action, $process, $updates): void {
            if ($updates !== []) {
                $action->update($updates);
            }

            if (array_key_exists('action_date', $updates)) {
                $maxActionDate = $process->actions()->max('action_date');
                $process->update([
                    'last_activity_date' => $maxActionDate,
                ]);
            }
        });

        return $action->refresh();
    }

    /**
     * @param  array<string, mixed>  $updates
     */
    private function assertTermRange(ProcessAction $action, array $updates): void
    {
        $start = array_key_exists('start_date', $updates)
            ? $updates['start_date']
            : $action->start_date?->format('Y-m-d');
        $end = array_key_exists('end_date', $updates)
            ? $updates['end_date']
            : $action->end_date?->format('Y-m-d');

        if (! is_string($start) || $start === '' || ! is_string($end) || $end === '') {
            return;
        }

        if ($end < $start) {
            throw ValidationException::withMessages([
                'term_end_date' => [__('process.term_end_before_start')],
            ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;

readonly class DeleteAdminProcessSubjectService
{
    /**
     * Removes a manual subject from a process. Judicial API subjects cannot be deleted.
     */
    public function handle(string $processId, string $subjectId): void
    {
        $process = Process::query()
            ->where('id', $processId)
            ->with(['subjects'])
            ->first();

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $subject = $process->subjects->firstWhere('id', $subjectId);

        if (! $subject instanceof ProcessSubject) {
            abort(404, __('process.subject_not_linked_to_process'));
        }

        if (! $subject->isManual()) {
            abort(422, __('process.subject_cannot_delete_judicial'));
        }

        $process->subjects()->detach($subjectId);

        if ($subject->processes()->count() === 0) {
            $subject->delete();
        }
    }
}

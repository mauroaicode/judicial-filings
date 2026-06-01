<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Support\Facades\DB;
use Src\Application\Admin\Process\Data\AdminProcessSubjectItemData;
use Src\Application\Admin\Process\Data\SyncAdminProcessSubjectsData;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;

readonly class SyncAdminProcessSubjectsService
{
    /**
     * Create or update process subjects for admin.
     * Items with `id` update an existing subject linked to the process (manual or from judicial API).
     * Items without `id` create a new manual subject (subject_registration_id = null) and attach it.
     */
    public function handle(string $processId, SyncAdminProcessSubjectsData $data): Process
    {
        $process = Process::query()->withSubjects()->find($processId);

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        DB::transaction(function () use ($process, $data): void {
            foreach ($data->subjects as $item) {
                $this->upsertSubject($process, $item);
            }
        });

        return $process->refresh()->load('subjects');
    }

    private function upsertSubject(Process $process, AdminProcessSubjectItemData $item): void
    {
        $subjectType = $this->formatSubjectType($item->subject_type);
        $name = StrParseHelper::toTitleCase(trim($item->name_or_business_name)) ?? trim($item->name_or_business_name);

        if ($item->id !== null && $item->id !== '') {
            $this->updateExistingSubject($process, $item->id, $subjectType, $name);

            return;
        }

        $this->createManualSubject($process, $subjectType, $name);
    }

    private function updateExistingSubject(
        Process $process,
        string $subjectId,
        string $subjectType,
        string $name,
    ): void {
        $subject = $process->subjects->firstWhere('id', $subjectId);

        if (! $subject instanceof ProcessSubject) {
            abort(404, __('process.subject_not_linked_to_process'));
        }

        $subject->update([
            'subject_type' => $subjectType,
            'name_or_business_name' => $name,
        ]);
    }

    private function createManualSubject(Process $process, string $subjectType, string $name): void
    {
        $existing = $process->subjects->first(
            fn (ProcessSubject $subject): bool => $subject->isManual()
                && $subject->subject_type === $subjectType
                && mb_strtolower(trim((string) $subject->name_or_business_name)) === mb_strtolower($name),
        );

        if ($existing instanceof ProcessSubject) {
            $existing->update([
                'subject_type' => $subjectType,
                'name_or_business_name' => $name,
            ]);

            return;
        }

        $subject = ProcessSubject::query()->create([
            'subject_registration_id' => null,
            'subject_type' => $subjectType,
            'is_cited' => false,
            'identification' => null,
            'name_or_business_name' => $name,
        ]);

        $process->subjects()->syncWithoutDetaching([$subject->id]);
        $process->load('subjects');
    }

    private function formatSubjectType(string $subjectType): string
    {
        $normalized = trim($subjectType);

        return mb_convert_case(mb_strtolower($normalized), MB_CASE_TITLE, 'UTF-8');
    }
}

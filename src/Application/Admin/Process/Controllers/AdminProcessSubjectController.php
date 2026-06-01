<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Admin\Process\Data\SyncAdminProcessSubjectsData;
use Src\Application\Admin\Process\Resources\AdminProcessSubjectResource;
use Src\Application\Admin\Process\Services\DeleteAdminProcessSubjectService;
use Src\Application\Admin\Process\Services\SyncAdminProcessSubjectsService;
use Src\Domain\Process\Models\ProcessSubject;

readonly class AdminProcessSubjectController
{
    public function __construct(
        private SyncAdminProcessSubjectsService $syncAdminProcessSubjectsService,
        private DeleteAdminProcessSubjectService $deleteAdminProcessSubjectService,
    ) {}

    /**
     * Create or update process subjects for a process (admin).
     *
     * Send `id` to update an existing subject (manual or judicial API).
     * Omit `id` to create a new manual subject (subject_registration_id = null, is_manual = true).
     * You may send only new items to append; existing subjects not in the payload are kept.
     */
    public function sync(string $processId, SyncAdminProcessSubjectsData $data): JsonResponse
    {
        $process = $this->syncAdminProcessSubjectsService->handle($processId, $data);

        $subjects = $process->subjects
            ->sort(function (ProcessSubject $a, ProcessSubject $b): int {
                $getPriority = function (string $type): int {
                    $type = mb_strtoupper($type);
                    if (str_contains($type, mb_strtoupper(ProcessSubject::TYPE_PLAINTIFF))) {
                        return 1;
                    }

                    if (str_contains($type, mb_strtoupper(ProcessSubject::TYPE_DEFENDANT))) {
                        return 2;
                    }

                    return 3;
                };

                $pA = $getPriority((string) $a->subject_type);
                $pB = $getPriority((string) $b->subject_type);

                if ($pA !== $pB) {
                    return $pA <=> $pB;
                }

                return strcasecmp((string) $a->name_or_business_name, (string) $b->name_or_business_name);
            })
            ->values()
            ->map(fn (ProcessSubject $subject): array => AdminProcessSubjectResource::fromModel($subject)->toArray());

        return response()->json([
            'message' => __('process.subjects_synced_successfully'),
            'subjects' => $subjects->all(),
        ]);
    }

    /**
     * Delete a manual subject from a process (admin). Judicial API subjects cannot be removed.
     */
    public function destroy(string $processId, string $subjectId): JsonResponse
    {
        $this->deleteAdminProcessSubjectService->handle($processId, $subjectId);

        return response()->json([
            'message' => __('process.subject_deleted_successfully'),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;

readonly class ProcessSubjectService
{
    public function __construct(
        private JudicialBranchConsultService $judicialBranchConsultService
    ) {}

    /**
     * Handle the process of fetching and saving process subjects.
     *
     * @param  Process  $process  The process to attach subjects to.
     * @param  int  $processId  The API process ID.
     */
    public function handle(Process $process, int $processId): void
    {
        $subjectsData = $this->fetchSubjectsFromJudicialBranch($processId);

        if ($subjectsData === []) {
            return;
        }

        $this->saveSubjects($process, $subjectsData);
    }

    /**
     * Fetch subjects from the judicial branch API.
     *
     * @param  int  $processId  The API process ID.
     * @return array<int, array<string, mixed>>
     */
    private function fetchSubjectsFromJudicialBranch(int $processId): array
    {
        $subjectsResponse = $this->judicialBranchConsultService->fetchSubjectsByProcess($processId);

        if (! $subjectsResponse->isSuccessful || empty($subjectsResponse->data)) {
            return [];
        }

        return $subjectsResponse->data;
    }

    /**
     * Save subjects to the database.
     *
     * @param  Process  $process  The process to attach subjects to.
     * @param  array<int, array<string, mixed>>  $subjectsData  The subject data from the API.
     */
    private function saveSubjects(Process $process, array $subjectsData): void
    {
        foreach ($subjectsData as $subjectData) {
            $this->createOrUpdateSubject($process, $subjectData);
        }
    }

    /**
     * Create or update a process subject record.
     *
     * @param  Process  $process  The process to attach the subject to.
     * @param  array<string, mixed>  $subjectData  The subject data from the API.
     */
    private function createOrUpdateSubject(Process $process, array $subjectData): void
    {
        $subjectRegistrationId = $subjectData['idRegSujeto'] ?? null;

        if (! $subjectRegistrationId) {
            return;
        }

        $existingSubject = ProcessSubject::query()
            ->whereProcessAndRegistrationId($process->id, $subjectRegistrationId)
            ->first();

        if ($existingSubject) {
            return;
        }

        ProcessSubject::query()->create([
            'process_id' => $process->id,
            'subject_registration_id' => $subjectRegistrationId,
            'subject_type' => $subjectData['tipoSujeto'] ?? '',
            'is_cited' => $subjectData['esEmplazado'] ?? false,
            'identification' => $subjectData['identificacion'] ?? null,
            'name_or_business_name' => $subjectData['nombreRazonSocial'] ?? '',
        ]);
    }
}

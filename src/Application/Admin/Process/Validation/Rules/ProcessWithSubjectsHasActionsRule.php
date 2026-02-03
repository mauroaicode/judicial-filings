<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Validation\Rules;

use Src\Application\Admin\Process\Validation\Contracts\ImportValidationRule;
use Src\Application\Admin\Process\Validation\ProcessImportDataForValidation;

/**
 * Cada proceso que tiene sujetos procesales debe tener al menos una actuación.
 */
final class ProcessWithSubjectsHasActionsRule implements ImportValidationRule
{
    public function ruleName(): string
    {
        return 'process_with_subjects_has_actions';
    }

    public function validate(ProcessImportDataForValidation $data): array
    {
        $processNumbersWithSubjects = $data->subjectRows
            ->pluck('process_number')
            ->map(fn ($n): string => (string) $n)
            ->unique()
            ->values()
            ->all();

        $processNumbersWithActions = $data->actionRows
            ->pluck('process_number')
            ->map(fn ($n): string => (string) $n)
            ->unique()
            ->values()
            ->all();

        $missing = array_diff($processNumbersWithSubjects, $processNumbersWithActions);
        if ($missing === []) {
            return [];
        }

        $errors = [];
        foreach ($missing as $processNumber) {
            $errors[] = [
                'message' => __('process.validation_process_with_subjects_without_actions', ['number' => $processNumber]),
                'details' => ['process_number' => $processNumber],
            ];
        }

        return $errors;
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Validation\Rules;

use Src\Application\Admin\Process\Validation\Contracts\ImportValidationRule;
use Src\Application\Admin\Process\Validation\ProcessImportDataForValidation;

/**
 * Cada proceso que tiene actuaciones debe tener al menos un sujeto procesal.
 */
final class ProcessWithActionsHasSubjectsRule implements ImportValidationRule
{
    public function ruleName(): string
    {
        return 'process_with_actions_has_subjects';
    }

    public function validate(ProcessImportDataForValidation $data): array
    {
        $processNumbersWithActions = $data->actionRows
            ->pluck('process_number')
            ->map(fn ($n): string => (string) $n)
            ->unique()
            ->values()
            ->all();

        $processNumbersWithSubjects = $data->subjectRows
            ->pluck('process_number')
            ->map(fn ($n): string => (string) $n)
            ->unique()
            ->values()
            ->all();

        $missing = array_diff($processNumbersWithActions, $processNumbersWithSubjects);
        if ($missing === []) {
            return [];
        }

        $errors = [];
        foreach ($missing as $processNumber) {
            $errors[] = [
                'message' => __('process.validation_process_with_actions_without_subjects', ['number' => $processNumber]),
                'details' => ['process_number' => $processNumber],
            ];
        }

        return $errors;
    }
}

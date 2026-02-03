<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Validation\Rules;

use Src\Application\Admin\Process\Validation\Contracts\ImportValidationRule;
use Src\Application\Admin\Process\Validation\ProcessImportDataForValidation;

/**
 * Cada proceso en la hoja Procesos debe tener al menos una fila en la hoja Organizaciones.
 */
final class ProcessHasOrganizationsRule implements ImportValidationRule
{
    public function ruleName(): string
    {
        return 'process_has_organizations';
    }

    public function validate(ProcessImportDataForValidation $data): array
    {
        $processNumbers = $data->processRows
            ->pluck('process_number')
            ->map(fn ($n): string => (string) $n)
            ->unique()
            ->values()
            ->all();

        $organizationProcessNumbers = $data->organizationRows
            ->pluck('process_number')
            ->map(fn ($n): string => (string) $n)
            ->unique()
            ->values()
            ->all();

        $missing = array_diff($processNumbers, $organizationProcessNumbers);
        if ($missing === []) {
            return [];
        }

        $errors = [];

        // Si la hoja Organizaciones está vacía o ningún proceso tiene organización, un solo mensaje claro
        if ($data->organizationRows->isEmpty()) {
            $errors[] = [
                'message' => __('process.validation_no_organizations_sheet'),
                'details' => [
                    'process_numbers_without_organization' => array_values($missing),
                    'count' => count($missing),
                ],
            ];

            return $errors;
        }

        // Uno o varios procesos sin organización: listar cuáles
        foreach ($missing as $processNumber) {
            $errors[] = [
                'message' => __('process.validation_process_without_organization', ['number' => $processNumber]),
                'details' => ['process_number' => $processNumber],
            ];
        }

        return $errors;
    }
}

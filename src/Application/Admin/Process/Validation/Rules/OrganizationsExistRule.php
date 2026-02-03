<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Validation\Rules;

use Src\Application\Admin\Process\Validation\Contracts\ImportValidationRule;
use Src\Application\Admin\Process\Validation\ProcessImportDataForValidation;
use Src\Domain\Organization\Models\Organization;

/**
 * Todas las organizaciones referenciadas en la hoja Organizaciones deben existir en la base de datos.
 */
final class OrganizationsExistRule implements ImportValidationRule
{
    public function ruleName(): string
    {
        return 'organizations_exist';
    }

    public function validate(ProcessImportDataForValidation $data): array
    {
        $organizationRows = $data->organizationRows;
        if ($organizationRows->isEmpty()) {
            return [];
        }

        $organizationIds = $organizationRows
            ->pluck('organization_id')
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        $existingIds = Organization::query()
            ->whereIn('id', $organizationIds)
            ->pluck('id')
            ->all();

        $missingIds = array_diff($organizationIds, $existingIds);
        if ($missingIds === []) {
            return [];
        }

        $errors = [];
        foreach ($organizationRows as $index => $row) {
            $id = (string) $row->get('organization_id');
            if (in_array($id, $missingIds, true)) {
                $errors[] = [
                    'message' => __('process.organization_not_found', ['id' => $id]),
                    'details' => ['row' => $index + 2, 'organization_id' => $id, 'process_number' => $row->get('process_number')],
                ];
            }
        }

        return $errors;
    }
}

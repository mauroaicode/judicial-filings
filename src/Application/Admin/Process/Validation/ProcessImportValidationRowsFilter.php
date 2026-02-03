<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Validation;

use Illuminate\Support\Collection;

/**
 * Filtra filas leídas del Excel por tipo de hoja para validación.
 */
final class ProcessImportValidationRowsFilter
{
    /**
     * Filas que pertenecen a la hoja Procesos (tienen process_id, court, process_type, etc.).
     *
     * @param  Collection<int, Collection<string, mixed>>  $rows
     * @return Collection<int, Collection<string, mixed>>
     */
    public static function processRows(Collection $rows): Collection
    {
        return $rows->filter(function ($row): bool {
            if (! $row->has('process_id') || ! $row->has('court') || ! $row->has('department')
                || ! $row->has('process_type') || ! $row->has('process_class')) {
                return false;
            }

            $processNumber = $row->get('process_number');

            return $processNumber !== null && $processNumber !== '' && preg_match('/^\d{23}$/', (string) $processNumber);
        })->values();
    }

    /**
     * Filas que pertenecen a la hoja Actuaciones.
     *
     * @param  Collection<int, Collection<string, mixed>>  $rows
     * @return Collection<int, Collection<string, mixed>>
     */
    public static function actionRows(Collection $rows): Collection
    {
        return $rows->filter(function ($row): bool {
            if (! $row->has('action_registration_id') || ! $row->has('action')) {
                return false;
            }

            $processNumber = $row->get('process_number');

            return $processNumber !== null && $processNumber !== '' && preg_match('/^\d{23}$/', (string) $processNumber)
                && $row->get('action_registration_id') !== null && $row->get('action') !== null
                && $row->get('action_date') !== null && $row->get('registration_date') !== null;
        })->values();
    }

    /**
     * Filas que pertenecen a la hoja Sujetos.
     *
     * @param  Collection<int, Collection<string, mixed>>  $rows
     * @return Collection<int, Collection<string, mixed>>
     */
    public static function subjectRows(Collection $rows): Collection
    {
        return $rows->filter(function ($row): bool {
            if (! $row->has('subject_registration_id') || ! $row->has('subject_type')) {
                return false;
            }

            $processNumber = $row->get('process_number');

            return $processNumber !== null && $processNumber !== '' && preg_match('/^\d{23}$/', (string) $processNumber)
                && $row->get('subject_registration_id') !== null && $row->get('subject_type') !== null
                && $row->get('name_or_business_name') !== null && $row->get('name_or_business_name') !== '';
        })->values();
    }

    /**
     * Filas que pertenecen a la hoja Organizaciones.
     *
     * @param  Collection<int, Collection<string, mixed>>  $rows
     * @return Collection<int, Collection<string, mixed>>
     */
    public static function organizationRows(Collection $rows): Collection
    {
        return $rows->filter(function ($row): bool {
            if (! $row->has('organization_id')) {
                return false;
            }

            $processNumber = $row->get('process_number');
            $organizationId = $row->get('organization_id');

            return $processNumber !== null && $processNumber !== '' && preg_match('/^\d{23}$/', (string) $processNumber)
                && $organizationId !== null && $organizationId !== '' && is_string($organizationId);
        })->values();
    }
}

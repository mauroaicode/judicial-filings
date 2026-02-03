<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Validation\Contracts;

use Src\Application\Admin\Process\Validation\ProcessImportDataForValidation;

/**
 * Regla de validación previa a la importación.
 * Cada regla recibe los datos leídos del Excel y devuelve una lista de errores (vacía si pasa).
 */
interface ImportValidationRule
{
    /**
     * Nombre de la regla (para identificar el error en la respuesta).
     */
    public function ruleName(): string;

    /**
     * Valida los datos. Retorna lista de errores; si está vacía, la regla pasa.
     *
     * @return list<array{message: string, details?: array}>
     */
    public function validate(ProcessImportDataForValidation $data): array;
}

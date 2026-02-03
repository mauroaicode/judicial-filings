<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Validation;

use Illuminate\Support\Collection;

/**
 * Datos leídos del Excel para validación (sin persistir).
 *
 * @property Collection<int, Collection<string, mixed>> $processRows
 * @property Collection<int, Collection<string, mixed>> $actionRows
 * @property Collection<int, Collection<string, mixed>> $subjectRows
 * @property Collection<int, Collection<string, mixed>> $organizationRows
 */
final readonly class ProcessImportDataForValidation
{
    public function __construct(
        public Collection $processRows,
        public Collection $actionRows,
        public Collection $subjectRows,
        public Collection $organizationRows,
    ) {}
}

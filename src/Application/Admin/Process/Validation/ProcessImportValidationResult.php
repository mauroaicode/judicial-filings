<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Validation;

/**
 * Resultado de la validación previa a la importación de procesos.
 *
 * @property bool $valid
 * @property list<array{rule: string, message: string, details?: array}> $errors
 */
final readonly class ProcessImportValidationResult
{
    public function __construct(
        public bool $valid,
        public array $errors = [],
    ) {}

    public static function ok(): self
    {
        return new self(true, []);
    }

    public static function fail(array $errors): self
    {
        return new self(false, $errors);
    }
}

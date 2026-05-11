<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\DTOs;

/**
 * One Excel row normalized for non–Rama-Judicial (private) synchronous import.
 */
readonly class PrivateProcessExcelImportedRowDTO
{
    public function __construct(
        public int $excelRowNumber,
        public string $court,
        public string $processNumber,
        public string $processClass,
        public string $plaintiffsRaw,
        public string $defendantsRaw,
        public string $actionText,
        public ?string $annotation,
        public ?string $startDate,
        public ?string $endDate,
        public ?string $registrationDate,
    ) {}
}

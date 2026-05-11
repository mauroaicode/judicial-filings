<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\DTOs;

readonly class PrivateProcessExcelParseResult
{
    /**
     * @param  list<PrivateProcessExcelImportedRowDTO>  $rows
     * @param  array<int, string>  $rowErrors  keyed by Excel row number (1-based)
     */
    public function __construct(
        public array $rows,
        public array $rowErrors,
    ) {}

    public function hasErrors(): bool
    {
        return $this->rowErrors !== [];
    }
}

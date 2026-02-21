<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\DTOs;

readonly class ProcessImportParseResult
{
    /**
     * @param  array<int, string>  $validNumbers  List of 23-digit process numbers (deduplicated).
     * @param  array<int, string>  $rowErrors  Excel row number (1-based) => error message.
     */
    public function __construct(
        public array $validNumbers,
        public array $rowErrors,
    ) {}

    public function hasErrors(): bool
    {
        return $this->rowErrors !== [];
    }

    public function isEmpty(): bool
    {
        return $this->validNumbers === [] && $this->rowErrors === [];
    }
}

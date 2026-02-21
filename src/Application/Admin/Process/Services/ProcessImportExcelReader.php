<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Src\Application\Admin\Process\DTOs\ProcessImportParseResult;

class ProcessImportExcelReader implements ToCollection
{
    /** @var array<int, string> */
    private array $validNumbers = [];

    /** @var array<int, string> */
    private array $rowErrors = [];

    public function __construct(
        private readonly UploadedFile $file,
    ) {}

    /** Parses the uploaded Excel file and returns deduplicated valid numbers and row errors. */
    public function parse(): ProcessImportParseResult
    {
        $this->validNumbers = [];
        $this->rowErrors = [];

        ExcelFacade::import($this, $this->file, null, $this->resolveFormat());

        return new ProcessImportParseResult(
            array_values(array_unique($this->validNumbers)),
            $this->rowErrors,
        );
    }

    /** Required by ToCollection: entry point for each sheet's rows. */
    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $hasHeader = $this->isHeaderRow((string) $rows->first()->get(0, ''));
        $dataRows = $hasHeader ? $rows->slice(1) : $rows;
        $startRow = $hasHeader ? 2 : 1;

        $this->processRows($dataRows, $startRow);
    }

    /** Determines XLS or XLSX format from the file extension. */
    private function resolveFormat(): string
    {
        return strtolower($this->file->getClientOriginalExtension()) === 'xls'
            ? Excel::XLS
            : Excel::XLSX;
    }

    /** Returns true if the cell value matches a known header label (e.g. "Radicación"). */
    private function isHeaderRow(string $firstCell): bool
    {
        $normalized = (string) preg_replace('/\s+/', '', trim($firstCell));

        return (bool) preg_match('/^radicaci[oó]n$/i', $normalized);
    }

    /** Iterates over data rows and processes each one with its 1-based Excel row number. */
    private function processRows(Collection $rows, int $startRow): void
    {
        $excelRow = $startRow;

        foreach ($rows as $row) {
            $this->processRow($row->get(0), $excelRow);
            $excelRow++;
        }
    }

    /** Validates a single cell and appends to valid numbers or row errors accordingly. */
    private function processRow(mixed $cell, int $excelRow): void
    {
        $value = $this->normalizeCell($cell);

        if ($value === '') {
            return;
        }

        if (! $this->isValidProcessNumber($value)) {
            $this->rowErrors[$excelRow] = __('process.import_row_invalid_digits', ['row' => $excelRow]);

            return;
        }

        $this->validNumbers[] = $value;
    }

    /** Strips surrounding whitespace and internal spaces from a cell value. */
    private function normalizeCell(mixed $cell): string
    {
        return (string) preg_replace('/\s+/', '', trim((string) ($cell ?? '')));
    }

    /** Returns true if the value is exactly 23 digits. */
    private function isValidProcessNumber(string $value): bool
    {
        return (bool) preg_match('/^\d{23}$/', $value);
    }
}

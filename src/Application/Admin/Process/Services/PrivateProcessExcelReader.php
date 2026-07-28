<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetExcelDate;
use Src\Application\Admin\Process\DTOs\PrivateProcessExcelImportedRowDTO;
use Src\Application\Admin\Process\DTOs\PrivateProcessExcelParseResult;
use Src\Application\Shared\Helpers\StrParseHelper;

class PrivateProcessExcelReader implements ToCollection
{
    /** @var list<PrivateProcessExcelImportedRowDTO> */
    private array $rows = [];

    /** @var array<int, string> */
    private array $rowErrors = [];

    /** @var array<string, int>|null canonical column key => 0-based index */
    private ?array $headerIndexMap = null;

    public function __construct(
        private readonly UploadedFile $file,
    ) {}

    public function parse(): PrivateProcessExcelParseResult
    {
        $this->rows = [];
        $this->rowErrors = [];
        $this->headerIndexMap = null;

        ExcelFacade::import($this, $this->file, null, $this->resolveFormat());

        return new PrivateProcessExcelParseResult($this->rows, $this->rowErrors);
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;

            if (! $row instanceof Collection) {
                continue;
            }

            if ($this->headerIndexMap === null) {
                $this->headerIndexMap = $this->resolveHeaderRow($row);
                if ($this->headerIndexMap === []) {
                    $this->rowErrors[$excelRow] = __('process.private_process_import_missing_headers');

                    return;
                }

                $missing = $this->missingRequiredColumns($this->headerIndexMap);
                if ($missing !== []) {
                    $this->rowErrors[$excelRow] = __('process.private_process_import_missing_columns', ['columns' => implode(', ', $missing)]);

                    return;
                }

                continue;
            }

            $this->parseDataRow($row, $excelRow);
        }
    }

    /** @param  array<string, int>  $map */
    private function missingRequiredColumns(array $map): array
    {
        // Actuación y fechas son opcionales: procesos "a estudio" aún sin historial.
        $required = ['court', 'radicacion', 'clase_proceso'];
        $missing = [];
        foreach ($required as $key) {
            if (! isset($map[$key])) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /** @param  Collection<int, mixed>  $row */
    private function parseDataRow(Collection $row, int $excelRow): void
    {
        $map = $this->headerIndexMap;
        if ($map === null) {
            return;
        }

        $radi = $this->normalizeProcessNumber((string) $this->cell($row, $map['radicacion'] ?? -1));

        $courtRaw = StrParseHelper::normalizeImportedLabel((string) $this->cell($row, $map['court'] ?? -1));

        $class = StrParseHelper::normalizeImportedLabel((string) $this->cell($row, $map['clase_proceso'] ?? -1));
        $plaintiffs = StrParseHelper::normalizeImportedLabel((string) $this->cell($row, $map['demandante'] ?? -1));
        $defendants = StrParseHelper::normalizeImportedLabel((string) $this->cell($row, $map['demandado'] ?? -1));

        $act = isset($map['actuacion'])
            ? StrParseHelper::normalizeImportedLabel((string) $this->cell($row, $map['actuacion']))
            : '';

        $annotation = null;
        if (isset($map['anotacion'])) {
            $annotationRaw = StrParseHelper::normalizeImportedLabel((string) $this->cell($row, $map['anotacion']));
            $annotation = $annotationRaw === '' ? null : $annotationRaw;
        }

        $startRaw = isset($map['fecha_inicial']) ? $this->cell($row, $map['fecha_inicial']) : null;
        $endRaw = isset($map['fecha_finalizacion']) ? $this->cell($row, $map['fecha_finalizacion']) : null;
        $regRaw = isset($map['fecha_registro']) ? $this->cell($row, $map['fecha_registro']) : null;

        $startDate = $this->parseFlexibleDate($startRaw);
        $endDate = $this->parseFlexibleDate($endRaw);
        $registrationDate = $this->parseFlexibleDate($regRaw);

        if ($radi === '') {
            $this->rowErrors[$excelRow] = __('process.import_row_invalid_digits', ['row' => $excelRow]);

            return;
        }

        if (! preg_match('/^\d{23}$/', $radi)) {
            $this->rowErrors[$excelRow] = __('process.import_row_invalid_digits', ['row' => $excelRow]);

            return;
        }

        if ($courtRaw === '') {
            $this->rowErrors[$excelRow] = __('process.private_process_import_row_empty_despacho', ['row' => $excelRow]);

            return;
        }

        if ($class === '') {
            $this->rowErrors[$excelRow] = __('process.private_process_import_row_empty_clase', ['row' => $excelRow]);

            return;
        }

        // Sin actuación: se crea el proceso; no se inventan fechas ni se crea ProcessAction.
        if ($act !== '') {
            if ($registrationDate === null || $registrationDate === '') {
                $registrationDate = $startDate ?? now()->format('Y-m-d');
            }

            if ($startDate === null || $startDate === '') {
                $startDate = $registrationDate;
            }

            if ($endDate === null || $endDate === '') {
                $endDate = $startDate;
            }
        } else {
            $startDate = null;
            $endDate = null;
            $registrationDate = null;
        }

        $this->rows[] = new PrivateProcessExcelImportedRowDTO(
            excelRowNumber: $excelRow,
            court: $courtRaw,
            processNumber: $radi,
            processClass: $class,
            plaintiffsRaw: $plaintiffs,
            defendantsRaw: $defendants,
            actionText: $act,
            annotation: $annotation,
            startDate: $startDate,
            endDate: $endDate,
            registrationDate: $registrationDate,
        );
    }

    private function resolveFormat(): string
    {
        return strtolower($this->file->getClientOriginalExtension()) === 'xls'
            ? Excel::XLS
            : Excel::XLSX;
    }

    /** @param  Collection<int, mixed>  $row */
    /** @return array<string, int> */
    private function resolveHeaderRow(Collection $row): array
    {
        /** @var array<string, int> $map */
        $map = [];
        foreach ($row as $columnIndex => $cell) {
            $label = $this->normalizeHeaderLabel((string) ($cell ?? ''));
            $key = $this->classifyHeaderColumn($label);
            if ($key !== null) {
                $map[$key] = (int) $columnIndex;
            }
        }

        return $map;
    }

    private function classifyHeaderColumn(string $label): ?string
    {
        if ($label === '' || preg_match('/^unnamed:\\d$/i', $label) === 1) {
            return null;
        }

        if ($this->labelMatches($label, ['despacho'])) {
            return 'court';
        }

        if ($this->labelMatches($label, ['radicación', 'radicacion'])) {
            return 'radicacion';
        }

        if ($this->labelMatches($label, ['clase proceso', 'clase de proceso'])) {
            return 'clase_proceso';
        }

        if ($this->labelMatches($label, ['demandante'])) {
            return 'demandante';
        }

        if ($this->labelMatches($label, ['demandado'])) {
            return 'demandado';
        }

        if ($this->labelMatches($label, ['actuación', 'actuacion'])) {
            return 'actuacion';
        }

        if ($this->labelMatches($label, ['anotación', 'anotacion'])) {
            return 'anotacion';
        }

        if ($this->labelMatches($label, ['fecha inicial', 'fecha inicia'])) {
            return 'fecha_inicial';
        }

        if ($this->labelMatches($label, ['fecha final', 'fecha finaliza', 'fecha finalización', 'fecha finaliz'])) {
            return 'fecha_finalizacion';
        }

        if ($this->labelMatches($label, ['fecha registro'])) {
            return 'fecha_registro';
        }

        return null;
    }

    private function labelMatches(string $normalizedLabel, array $patterns): bool
    {
        foreach ($patterns as $needle) {
            if (mb_strpos($normalizedLabel, (string) $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeaderLabel(string $cell): string
    {
        $s = mb_strtolower(trim($cell));

        return (string) preg_replace('/\s+/u', ' ', $s);
    }

    private function normalizeProcessNumber(string $cell): string
    {
        $digits = preg_replace('/\D+/', '', $cell);

        return $digits ?? '';
    }

    /** @param  Collection<int, mixed>  $row */
    private function cell(Collection $row, int $index): mixed
    {
        return $index >= 0 ? $row->get($index) : null;
    }

    private function parseFlexibleDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            try {
                return Date::parse($value->format('Y-m-d'))->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        if (is_numeric($value)) {
            $number = (float) $value;
            if ($number > 20000 && $number < 120000) {
                try {
                    return SpreadsheetExcelDate::excelToDateTimeObject($number)->format('Y-m-d');
                } catch (\Throwable) {
                }
            }

            try {
                return Date::createFromTimestamp((int) $number)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Date::parse(trim((string) $value))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}

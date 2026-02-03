<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Excel;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ExcelImportService
{
    /**
     * Import Excel file using the provided import class instance.
     *
     * @param  object  $importClass  Instance of an import class (ToCollection, WithChunkReading, etc.)
     * @param  UploadedFile|string  $file  The Excel file to import (UploadedFile or file path)
     * @param  string|null  $sheetName  Sheet name (ignored for import; use WithMultipleSheets in import class for sheet selection)
     * @param  string  $format  File format: 'csv' or 'xlsx' (default: 'xlsx')
     */
    public static function import(
        object $importClass,
        UploadedFile|string $file,
        ?string $sheetName = null,
        string $format = 'xlsx'
    ): void {
        $excelFormat = match ($format) {
            'xlsx' => \Maatwebsite\Excel\Excel::XLSX,
            default => \Maatwebsite\Excel\Excel::CSV,
        };

        Excel::import($importClass, $file, null, $excelFormat);
    }

    /**
     * Read a single sheet to a collection (no persistence). Use for validation.
     *
     * @return Collection<int, Collection<string, mixed>>
     */
    public static function readSheetToCollection(
        UploadedFile|string $file,
        string $sheetName,
        string $format = 'xlsx'
    ): Collection {
        $excelFormat = match ($format) {
            'xlsx' => \Maatwebsite\Excel\Excel::XLSX,
            default => \Maatwebsite\Excel\Excel::CSV,
        };

        $reader = new \Src\Application\Admin\Process\Imports\SingleSheetToCollectionReader($sheetName);
        Excel::import($reader, $file, null, $excelFormat);

        return $reader->rows;
    }
}

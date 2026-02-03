<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Src\Application\Admin\Process\Services\ProcessImportService;

class ProcessImport implements ToCollection, WithChunkReading, WithHeadingRow, WithMultipleSheets
{
    public function __construct(
        private readonly ProcessImportService $service
    ) {}

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $validRows = $rows->filter(function ($row): bool {
            if (! $row->has('process_id') || ! $row->has('court') || ! $row->has('department')
                || ! $row->has('process_type') || ! $row->has('process_class')) {
                return false;
            }

            $processNumber = $row->get('process_number');
            if (empty($processNumber) || ! preg_match('/^\d{23}$/', (string) $processNumber)) {
                return false;
            }

            if (empty($row->get('process_id')) || empty($row->get('court')) || empty($row->get('department'))
                || empty($row->get('process_type')) || empty($row->get('process_class'))) {
                return false;
            }

            $processDate = $row->get('process_date');

            return ! empty($processDate);
        });

        if ($validRows->isEmpty()) {
            return;
        }

        $this->service->processProcessRows($validRows);
    }

    public function chunkSize(): int
    {
        return 1500;
    }

    /**
     * @return array<string, object>
     */
    public function sheets(): array
    {
        return ['Procesos' => $this];
    }
}

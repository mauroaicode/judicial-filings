<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Src\Application\Admin\Process\Services\ProcessImportService;

class ProcessSubjectImport implements ToCollection, WithChunkReading, WithHeadingRow, WithMultipleSheets
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
            if (! $row->has('subject_registration_id') || ! $row->has('subject_type')) {
                return false;
            }

            $processNumber = $row->get('process_number');
            if (empty($processNumber) || ! preg_match('/^\d{23}$/', (string) $processNumber)) {
                return false;
            }

            if (empty($row->get('subject_registration_id')) || empty($row->get('subject_type'))) {
                return false;
            }

            $nameOrBusinessName = $row->get('name_or_business_name');

            return ! empty($nameOrBusinessName);
        });

        if ($validRows->isEmpty()) {
            return;
        }

        $this->service->processSubjectRows($validRows);
    }

    public function chunkSize(): int
    {
        return 2000;
    }

    /**
     * @return array<string, object>
     */
    public function sheets(): array
    {
        return ['Sujetos' => $this];
    }
}

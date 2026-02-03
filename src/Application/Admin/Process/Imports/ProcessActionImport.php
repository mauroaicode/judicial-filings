<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Src\Application\Admin\Process\Services\ProcessImportService;

class ProcessActionImport implements ToCollection, WithChunkReading, WithHeadingRow, WithMultipleSheets
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
            if (! $row->has('action_registration_id') || ! $row->has('action')) {
                return false;
            }

            $processNumber = $row->get('process_number');
            if (empty($processNumber) || ! preg_match('/^\d{23}$/', (string) $processNumber)) {
                return false;
            }

            return ! (empty($row->get('action_registration_id')) || empty($row->get('action')) || empty($row->get('action_date')) || empty($row->get('registration_date')));
        });

        if ($validRows->isEmpty()) {
            return;
        }

        $this->service->processActionRows($validRows);
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
        return ['Actuaciones' => $this];
    }
}

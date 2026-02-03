<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Src\Application\Admin\Process\Services\ProcessImportService;

class ProcessOrganizationImport implements ToCollection, WithChunkReading, WithHeadingRow, WithMultipleSheets
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
            if (! $row->has('organization_id')) {
                return false;
            }

            $processNumber = $row->get('process_number');
            if (empty($processNumber) || ! preg_match('/^\d{23}$/', (string) $processNumber)) {
                return false;
            }

            $organizationId = $row->get('organization_id');

            return ! empty($organizationId) && is_string($organizationId);
        });

        if ($validRows->isEmpty()) {
            return;
        }

        $this->service->processOrganizationRows($validRows);
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
        return ['Organizaciones' => $this];
    }
}

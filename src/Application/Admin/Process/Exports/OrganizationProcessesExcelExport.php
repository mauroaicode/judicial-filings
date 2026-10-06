<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Exports;

use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Src\Application\Admin\Process\Data\OrganizationProcessExportData;
use Src\Application\Admin\Process\Services\OrganizationProcessExportQueryService;

class OrganizationProcessesExcelExport implements FromGenerator, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithTitle
{
    private int $rowNumber = 0;

    public function __construct(
        private readonly string $organizationId,
        private readonly OrganizationProcessExportData $filters,
        private readonly OrganizationProcessExportQueryService $queryService,
    ) {}

    public function title(): string
    {
        return 'Procesos';
    }

    public function generator(): Generator
    {
        foreach ($this->queryService->yieldRows($this->organizationId, $this->filters) as $row) {
            $this->rowNumber++;

            yield [$this->rowNumber, ...array_values($row)];
        }
    }

    public function rowCount(): int
    {
        return $this->rowNumber;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        $headings = [
            '#',
            'Número de radicado',
            'Despacho',
            'Clase de proceso',
            'Rol abogado',
            'Estado',
        ];

        if ($this->filters->include_plaintiffs) {
            $headings[] = 'Demandante';
        }

        if ($this->filters->include_defendants) {
            $headings[] = 'Demandado';
        }

        if ($this->filters->include_other_subjects) {
            $headings[] = 'Otros sujetos';
        }

        return $headings;
    }
}

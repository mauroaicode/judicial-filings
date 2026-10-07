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
use Src\Application\Admin\Process\Support\OrganizationProcessExportActionQueryService;

class OrganizationProcessActionsExcelExport implements FromGenerator, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithTitle
{
    private int $rowNumber = 0;

    public function __construct(
        private readonly string $organizationId,
        private readonly OrganizationProcessExportData $filters,
        private readonly OrganizationProcessExportActionQueryService $actionQueryService,
    ) {}

    public function title(): string
    {
        return 'Actuaciones';
    }

    public function generator(): Generator
    {
        foreach ($this->actionQueryService->yieldRows($this->organizationId, $this->filters) as $row) {
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
        return [
            '#',
            'Número de radicado',
            'Despacho',
            'Fecha actuación',
            'Actuación',
            'Anotación',
            'Fecha registro',
            'Fecha inicio',
            'Fecha fin',
            'Consecutivo',
        ];
    }
}

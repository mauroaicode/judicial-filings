<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Excel;

use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Src\Application\Shared\Process\Data\ProcessActionFilterData;
use Src\Domain\Process\Models\Process;

class ProcessActionsExcelExport implements FromGenerator, WithHeadings, WithStrictNullComparison, WithTitle
{
    private int $rowNumber = 0;

    public function __construct(
        private readonly Process $process,
        private readonly ProcessActionFilterData $filters,
        private readonly CollectProcessActionsExcelRowsService $rowsService,
    ) {}

    public function title(): string
    {
        return 'Actuaciones';
    }

    public function generator(): Generator
    {
        foreach ($this->rowsService->yieldRows($this->process, $this->filters) as $row) {
            $this->rowNumber++;

            yield [$this->rowNumber, ...array_values($row)];
        }
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
            'Fecha inicio término',
            'Fecha fin término',
            'Consecutivo',
            'Retirada en Rama',
        ];
    }
}

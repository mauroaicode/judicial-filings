<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Lee una sola hoja de Excel por nombre a una colección (sin persistir).
 * Usa WithMultipleSheets para que Laravel Excel cargue solo esa hoja.
 */
class SingleSheetToCollectionReader implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    /** @var Collection<int, Collection<string, mixed>> */
    public Collection $rows;

    public function __construct(
        private readonly string $sheetName
    ) {
        $this->rows = collect([]);
    }

    /**
     * @return array<string, object> sheet name => import instance (this)
     */
    public function sheets(): array
    {
        return [$this->sheetName => $this];
    }

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }
}

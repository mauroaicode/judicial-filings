<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Lee una hoja de Excel a una colección (sin persistir).
 * Usado para validación previa a la importación.
 */
class SheetToCollectionReader implements ToCollection, WithHeadingRow
{
    /** @var Collection<int, Collection<string, mixed>> */
    public Collection $rows;

    public function __construct()
    {
        $this->rows = collect([]);
    }

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }
}

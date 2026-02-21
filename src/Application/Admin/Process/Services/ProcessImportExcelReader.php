<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Excel;
use Src\Application\Admin\Process\DTOs\ProcessImportParseResult;

class ProcessImportExcelReader implements ToCollection
{
    /** @var array<int, string> */
    private array $validNumbers = [];

    /** @var array<int, string> */
    private array $rowErrors = [];

    private int $dataStartRow = 1;

    public function __construct(
        private readonly UploadedFile $file
    ) {}

    public function parse(): ProcessImportParseResult
    {
        $this->validNumbers = [];
        $this->rowErrors = [];

        $excelFormat = strtolower($this->file->getClientOriginalExtension()) === 'xls'
            ? Excel::XLS
            : Excel::XLSX;

        \Maatwebsite\Excel\Facades\Excel::import($this, $this->file, null, $excelFormat);

        $unique = array_values(array_unique($this->validNumbers));

        return new ProcessImportParseResult($unique, $this->rowErrors);
    }

    /**
     * @param  Collection<int, Collection<int, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $firstCell = (string) $rows->first()->get(0, '');
        $strip = trim((string) preg_replace('/\s+/', '', $firstCell));
        if (preg_match('/^radicaci[oó]n$/i', $strip)) {
            $this->dataStartRow = 2;
            $rows = $rows->slice(1);
        } else {
            $this->dataStartRow = 1;
        }

        $excelRow = $this->dataStartRow;
        foreach ($rows as $row) {
            $cell = $row->get(0);
            $value = trim((string) ($cell ?? ''));
            if ($value === '') {
                $excelRow++;

                continue;
            }

            $normalized = preg_replace('/\s+/', '', $value);
            if ($normalized === '') {
                $excelRow++;

                continue;
            }

            if (! preg_match('/^\d{23}$/', (string) $normalized)) {
                $this->rowErrors[$excelRow] = __('process.import_row_invalid_digits', ['row' => $excelRow]);
            } else {
                $this->validNumbers[] = $normalized;
            }

            $excelRow++;
        }
    }
}

<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Src\Application\Admin\Process\Services\PrivateProcessExcelReader;

afterEach(function (): void {
    $path = $this->privateReaderSpreadsheetTmp ?? null;
    if (is_string($path) && $path !== '' && file_exists($path)) {
        unlink($path);
    }
});

function makePrivateProcessExcelWithExtraSheet(): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Procesos Judiciales');

    $headers = [
        'Despacho',
        'Radicación',
        'Clase Proceso',
        'Demandante',
        'Demandado',
        'Actuación',
        'Anotación',
        'Fecha Inicial',
        'Fecha Finalización',
        'Fecha Registro',
    ];

    foreach ($headers as $i => $title) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $title);
    }

    $sheet->setCellValue('A2', 'Juzgado 001 Civil Municipal de Jamundi');
    $sheet->setCellValueExplicit('B2', '76364400300120260040900', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('C2', 'MONITORIO');
    $sheet->setCellValue('D2', 'DEMANDANTE TEST');
    $sheet->setCellValue('E2', 'DEMANDADO TEST');
    $sheet->setCellValue('F2', 'Fijacion estado Auto RECHAZA');
    $sheet->setCellValue('H2', '2026-08-03');
    $sheet->setCellValue('I2', '2026-08-03');
    $sheet->setCellValue('J2', '2026-07-31');

    // Extra sheet like the real "Hoja1" export — only court names, no radicados.
    $extra = $spreadsheet->createSheet();
    $extra->setTitle('Hoja1');
    $extra->setCellValue('A2', 'Juzgado 001 Promiscuo Municipal de Dagua');
    $extra->setCellValue('A3', 'Juzgado 001 Civil Municipal de Jamundi');

    $tmp = tempnam(sys_get_temp_dir(), 'private-reader-').'.xlsx';
    (new Xlsx($spreadsheet))->save($tmp);
    test()->privateReaderSpreadsheetTmp = $tmp;

    return new UploadedFile(
        $tmp,
        'Libro-multi-hoja.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );
}

it('ignores secondary sheets without process headers so they do not fail validation', function (): void {
    $parsed = (new PrivateProcessExcelReader(makePrivateProcessExcelWithExtraSheet()))->parse();

    expect($parsed->rowErrors)->toBe([])
        ->and($parsed->rows)->toHaveCount(1)
        ->and($parsed->rows[0]->processNumber)->toBe('76364400300120260040900');
});

it('parses the real publicaciones workbook that includes an unused Hoja1', function (): void {
    $path = base_path('docs/Libro 1 2026-08-03.xlsx');
    if (! is_file($path)) {
        $this->markTestSkipped('Fixture docs/Libro 1 2026-08-03.xlsx is not present.');
    }

    $file = new UploadedFile(
        $path,
        'Libro 1 2026-08-03.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $parsed = (new PrivateProcessExcelReader($file))->parse();

    expect($parsed->rowErrors)->toBe([])
        ->and(count($parsed->rows))->toBeGreaterThan(100)
        ->and($parsed->rows[0]->processNumber)->toBe('76364400300120260040900');
});

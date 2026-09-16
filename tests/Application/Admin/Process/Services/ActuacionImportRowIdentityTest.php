<?php

declare(strict_types=1);

use Src\Application\Admin\Process\DTOs\PrivateProcessExcelImportedRowDTO;
use Src\Application\Admin\Process\Services\ActuacionImportRowIdentity;

it('keeps two identical publicaciones rows as separate actuaciones', function (): void {
    $rows = [
        row(),
        row(),
    ];

    $distinguished = (new ActuacionImportRowIdentity)->distinguish($rows);

    expect($distinguished[0]->annotation)->toBe('Publicación 1')
        ->and($distinguished[1]->annotation)->toBe('Publicación 2');
});

it('distinguishes actuaciones by auto number, document name and parties', function (): void {
    $identity = new ActuacionImportRowIdentity;

    $byAuto = $identity->distinguish([
        row(auto: '844'),
        row(auto: '845'),
    ]);

    expect($byAuto[0]->annotation)->toBe('Auto 844')
        ->and($byAuto[1]->annotation)->toBe('Auto 845');

    $byDocument = $identity->distinguish([
        row(document: 'AC1079Rad20230012000ResuelveVariosAsuntos.pdf'),
        row(document: 'AC1078Rad20230012000ReconocePersoneriaYResuelveRecurso.pdf'),
    ]);

    expect($byDocument[0]->annotation)->toContain('AC1079')
        ->and($byDocument[1]->annotation)->toContain('AC1078');

    $byParties = $identity->distinguish([
        row(plaintiffs: 'INSTITUTO DE FINANCIAMIENTO', defendants: 'JORGE ENRIQUE'),
        row(plaintiffs: 'REDES IMAT CLINICA', defendants: 'JORGE ENRIQUE'),
    ]);

    expect($byParties[0]->annotation)->toContain('INSTITUTO DE FINANCIAMIENTO')
        ->and($byParties[1]->annotation)->toContain('REDES IMAT CLINICA');
});

function row(
    string $auto = '',
    string $document = '',
    string $plaintiffs = 'TL Ingeambiente SAS',
    string $defendants = 'Municipio de Guacari',
): PrivateProcessExcelImportedRowDTO {
    return new PrivateProcessExcelImportedRowDTO(
        excelRowNumber: 2,
        court: 'Juzgado',
        processNumber: '76111310500120150000700',
        processClass: 'ORDINARIO',
        plaintiffsRaw: $plaintiffs,
        defendantsRaw: $defendants,
        actionText: 'Auto',
        annotation: null,
        startDate: '2026-08-11',
        endDate: '2026-08-11',
        registrationDate: '2026-08-11',
        documentName: $document !== '' ? $document : null,
        autoNumber: $auto !== '' ? $auto : null,
    );
}

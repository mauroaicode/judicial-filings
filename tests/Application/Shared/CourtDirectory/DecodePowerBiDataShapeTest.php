<?php

declare(strict_types=1);

use Src\Application\Shared\CourtDirectory\CourtDirectorySyncException;
use Src\Application\Shared\CourtDirectory\DecodePowerBiDataShape;

it('decodes repeated cells and literal values into directory rows', function (): void {
    $rows = (new DecodePowerBiDataShape)->decode([
        'results' => [[
            'result' => [
                'data' => [
                    'dsr' => [
                        'DS' => [[
                            'ValueDicts' => [
                                'D0' => ['a@cendoj.ramajudicial.gov.co', 'b@cendoj.ramajudicial.gov.co'],
                                'D1' => ['Juzgado 001', 'Juzgado 002'],
                                'D2' => ['Juzgado De Circuito'],
                                'D3' => ['Laboral'],
                                'D4' => ['Valle'],
                                'D5' => ['Cali'],
                                'D6' => ['Área'],
                                'D7' => ['760014189001', '760014189002'],
                            ],
                            'PH' => [[
                                'DM0' => [
                                    ['C' => [0, 0, 0, 0, 0, 0, 0, 0]],
                                    ['C' => [1, 1, 1], 'R' => 124],
                                    ['C' => ['c@cendoj.ramajudicial.gov.co', 1, 0, 0, 0, 0, 0, 1]],
                                ],
                            ]],
                        ]],
                    ],
                ],
            ],
        ]],
    ], [
        'EMAIL',
        'NOMBRE',
        'EMPRESA',
        'DEPARTAMENTO',
        'Estado Provincia',
        'CIUDAD',
        'TIPO DE CUENTA',
        'CODIGO DE DESPACHO',
    ]);

    expect($rows)->toHaveCount(3)
        ->and($rows[0]->email)->toBe('a@cendoj.ramajudicial.gov.co')
        ->and($rows[0]->specialty)->toBe('Laboral')
        ->and($rows[0]->department)->toBe('Valle')
        ->and($rows[0]->deskCode)->toBe('760014189001')
        ->and($rows[1]->email)->toBe('b@cendoj.ramajudicial.gov.co')
        ->and($rows[1]->specialty)->toBe('Laboral')
        ->and($rows[1]->corporation)->toBe('Juzgado De Circuito')
        ->and($rows[1]->deskCode)->toBe('760014189002')
        ->and($rows[2]->email)->toBe('c@cendoj.ramajudicial.gov.co')
        ->and($rows[2]->name)->toBe('Juzgado 002')
        ->and($rows[2]->deskCode)->toBe('760014189002');
});

it('rejects a directory response without the desk code column', function (): void {
    expect(fn () => (new DecodePowerBiDataShape)->decode(['results' => []], ['EMAIL']))
        ->toThrow(CourtDirectorySyncException::class);
});

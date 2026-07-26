<?php

declare(strict_types=1);

use Src\Application\Shared\Helpers\SamaiCourtNameHelper;

it('prefers SAMAI Origen field for despacho', function (): void {
    $court = SamaiCourtNameHelper::build([
        'Origen' => 'JUZGADO 002 ADMINISTRATIVO DE GUADALAJARA DE BUGA',
        'Ponente' => 'HECTOR ALFREDO ALMEIDA TENA',
        'NombreSalaDecision' => 'Tribunal Administrativo del Valle del Cauca',
        'cityName' => 'VALLE',
    ]);

    expect($court)->toBe('JUZGADO 002 ADMINISTRATIVO DE GUADALAJARA DE BUGA');
});

it('keeps the court number from prefixed Origen labels', function (): void {
    $court = SamaiCourtNameHelper::build([
        'Origen' => 'Juzgado Administrativo 001 JUZGADO ADMINISTRATIVO DE AGUACHICA (CESAR)',
        'NombreSalaDecision' => 'Juzgado Administrativo',
        'cityName' => 'CESAR',
    ]);

    expect($court)->toBe('JUZGADO 001 ADMINISTRATIVO DE AGUACHICA (CESAR)');
});

it('normalizes prefixed EntidadRadicadora origen labels keeping the number', function (): void {
    $court = SamaiCourtNameHelper::build([
        'EntidadRadicadora' => 'Juzgado Administrativo 014 JUZGADO ADMINISTRATIVO DE CALI (VALLE)',
        'Ponente' => 'HECTOR ALFREDO ALMEIDA TENA',
        'NombreSalaDecision' => 'Tribunal Administrativo del Valle del Cauca',
    ]);

    expect($court)->toBe('JUZGADO 014 ADMINISTRATIVO DE CALI (VALLE)');
});

it('drops placeholder 000 from tribunal-prefixed origen', function (): void {
    $court = SamaiCourtNameHelper::build([
        'Origen' => 'Tribunal Administrativo 000 JUZGADO ADMINISTRATIVO DE ARMENIA',
    ]);

    expect($court)->toBe('JUZGADO ADMINISTRATIVO DE ARMENIA');
});

it('ignores numeric EntidadRadicadora codes from REST', function (): void {
    $court = SamaiCourtNameHelper::build([
        'EntidadRadicadora' => '760013300014',
        'Ponente' => 'JUZGADO 14 ADMINISTRATIVO DE CALI',
    ]);

    expect($court)->toBe('JUZGADO 14 ADMINISTRATIVO DE CALI');
});

it('falls back to NombreSalaDecision when origen is missing', function (): void {
    $court = SamaiCourtNameHelper::build([
        'Ponente' => 'HECTOR ALFREDO ALMEIDA TENA',
        'NombreSalaDecision' => 'Tribunal Administrativo del Valle del Cauca',
        'cityName' => 'VALLE',
    ]);

    expect($court)->toBe('Tribunal Administrativo del Valle del Cauca');
});

it('scrubs the CaliI typo from SAMAI court names', function (): void {
    $court = SamaiCourtNameHelper::build([
        'Ponente' => 'Juzgado 14 Administrativo de CaliI',
    ]);

    expect($court)->toBe('Juzgado 14 Administrativo de Cali');
});

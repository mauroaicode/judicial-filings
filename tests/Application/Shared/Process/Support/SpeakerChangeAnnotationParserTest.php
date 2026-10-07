<?php

declare(strict_types=1);

use Src\Application\Shared\Process\Support\SpeakerChangeAnnotationParser;

it('parses previous and new speaker from a cambio de ponente annotation', function (): void {
    $parsed = SpeakerChangeAnnotationParser::parse(
        'Cambio de ponente',
        'OUT-Nuevo titular del despacho.-Ponente anterior: FERNANDO AUGUSTO GARCIA MUÑOZ (E) Ponente nuevo:JOHN ALEXANDER HURTADO PAREDES',
    );

    expect($parsed)->toMatchArray([
        'previous' => 'FERNANDO AUGUSTO GARCIA MUÑOZ (E)',
        'new' => 'JOHN ALEXANDER HURTADO PAREDES',
    ]);
});

it('parses when only ponente nuevo is present', function (): void {
    $parsed = SpeakerChangeAnnotationParser::parse(
        'Cambio de ponente',
        'Ponente nuevo:EDGAR GUILLERMO CABRERA RAMOS',
    );

    expect($parsed)->toMatchArray([
        'previous' => null,
        'new' => 'EDGAR GUILLERMO CABRERA RAMOS',
    ]);
});

it('returns null when annotation has no ponente nuevo', function (): void {
    expect(SpeakerChangeAnnotationParser::parse(
        'Suspensión de términos',
        'Se suspenden los términos judiciales',
    ))->toBeNull();
});

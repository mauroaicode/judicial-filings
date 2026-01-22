<?php

declare(strict_types=1);

use Src\Application\Shared\Helpers\StrParseHelper;

it('converts uppercase text to title case', function (): void {
    expect(StrParseHelper::toTitleCase('JUAN PEREZ'))->toBe('Juan Perez');
    expect(StrParseHelper::toTitleCase('MARIA GARCIA'))->toBe('Maria Garcia');
});

it('handles lowercase words correctly', function (): void {
    expect(StrParseHelper::toTitleCase('UNIDAD ADMINISTRATIVA ESPECIAL DE GESTION PENSIONAL Y CONTRIBUCIONES PARAFISCALES'))
        ->toBe('Unidad Administrativa Especial de Gestion Pensional y Contribuciones Parafiscales');

    expect(StrParseHelper::toTitleCase('EMPRESA DE SERVICIOS Y CONSULTORIA'))
        ->toBe('Empresa de Servicios y Consultoria');
});

it('preserves abbreviations in uppercase', function (): void {
    expect(StrParseHelper::toTitleCase('EMPRESA XYZ S.A.'))->toBe('Empresa Xyz S.A.');
    expect(StrParseHelper::toTitleCase('COMPAÑIA ABC S.A.S.'))->toBe('Compañia Abc S.A.S.');
    expect(StrParseHelper::toTitleCase('SOCIEDAD LTDA.'))->toBe('Sociedad LTDA.');
});

it('handles null input', function (): void {
    expect(StrParseHelper::toTitleCase(null))->toBeNull();
});

it('handles empty string', function (): void {
    expect(StrParseHelper::toTitleCase(''))->toBeNull();
    expect(StrParseHelper::toTitleCase('   '))->toBeNull();
});

it('handles single word', function (): void {
    expect(StrParseHelper::toTitleCase('JUAN'))->toBe('Juan');
    expect(StrParseHelper::toTitleCase('EMPRESA'))->toBe('Empresa');
});

it('handles text with multiple spaces', function (): void {
    expect(StrParseHelper::toTitleCase('JUAN   PEREZ'))->toBe('Juan Perez');
    expect(StrParseHelper::toTitleCase('  EMPRESA XYZ  '))->toBe('Empresa Xyz');
});

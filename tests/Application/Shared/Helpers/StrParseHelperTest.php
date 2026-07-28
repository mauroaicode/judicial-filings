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

it('strips accents but keeps ñ', function (): void {
    expect(StrParseHelper::stripAccentsKeepEnie('JUZGADO ADMINISTRATIVO DE IBAGUÉ'))
        ->toBe('JUZGADO ADMINISTRATIVO DE IBAGUE');
    expect(StrParseHelper::stripAccentsKeepEnie('CARMEN ELENA MEDINA MUÑOZ'))
        ->toBe('CARMEN ELENA MEDINA MUÑOZ');
    expect(StrParseHelper::stripAccentsKeepEnie('ÁÉÍÓÚáéíóú'))
        ->toBe('AEIOUaeiou');
});

it('normalizes imported labels to title case without accents', function (): void {
    expect(StrParseHelper::normalizeImportedLabel('JUZGADO 001 PROMISCUO MUNICIPAL DE DAGUA'))
        ->toBe('Juzgado 001 Promiscuo Municipal de Dagua');
    expect(StrParseHelper::normalizeImportedLabel('CARMEN ELENA MEDINA MUÑOZ'))
        ->toBe('Carmen Elena Medina Muñoz');
    expect(StrParseHelper::normalizeImportedLabel('VERBAL PERTENENCIA (LEY 1561 DE 2012)'))
        ->toBe('Verbal Pertenencia (ley 1561 de 2012)');
    expect(StrParseHelper::normalizeImportedLabel(''))->toBe('');
    expect(StrParseHelper::normalizeImportedLabel(null))->toBe('');
});

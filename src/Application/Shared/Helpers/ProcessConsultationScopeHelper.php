<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

/**
 * Decide qué fuentes consultar según el radicado (CUIN) y/o el despacho.
 *
 * CUIN 23 dígitos: DD-CCC-EE-SS-DDD-AAAA-NNNNN-II
 *  - EE entidad (posiciones 6-7)
 *  - SS especialidad (posiciones 8-9)
 *
 * SAMAI (Consejo de Estado / administrativos) no aplica a civiles, laborales, etc.
 */
final class ProcessConsultationScopeHelper
{
    /**
     * Rama Judicial no lo tiene (o está privado) → ¿tiene sentido ir a SAMAI?
     */
    public static function shouldConsultSamai(string $processNumber, ?string $court = null): bool
    {
        if (self::courtLooksSamaiEligible($court)) {
            return true;
        }

        return self::radicadoLooksSamaiEligible($processNumber);
    }

    /**
     * Laborales se siguen en Unificada y también reciben Excel de publicaciones procesales.
     */
    public static function isLaboral(string $processNumber, ?string $court = null): bool
    {
        if (self::courtLooksLaboral($court)) {
            return true;
        }

        return self::radicadoLooksLaboral($processNumber);
    }

    private static function courtLooksSamaiEligible(?string $court): bool
    {
        $normalized = mb_strtolower(trim((string) $court));
        if ($normalized === '') {
            return false;
        }

        if (str_contains($normalized, 'consejo de estado')) {
            return true;
        }

        return str_contains($normalized, 'administrativ');
    }

    private static function courtLooksLaboral(?string $court): bool
    {
        $normalized = mb_strtolower(trim((string) $court));

        return $normalized !== '' && str_contains($normalized, 'laboral');
    }

    private static function radicadoLooksSamaiEligible(string $processNumber): bool
    {
        $digits = self::digits($processNumber);
        if ($digits === null) {
            return false;
        }

        $entity = substr($digits, 5, 2);
        $specialty = substr($digits, 7, 2);

        // 03 = Consejo de Estado; 33 = juzgados administrativos;
        // 23 + especialidad 33 = tribunal administrativo.
        return $entity === '03'
            || $entity === '33'
            || ($entity === '23' && $specialty === '33');
    }

    private static function radicadoLooksLaboral(string $processNumber): bool
    {
        $digits = self::digits($processNumber);
        if ($digits === null) {
            return false;
        }

        // Especialidad 05: laboral (circuito 31-05, municipal 41-05, etc.).
        return substr($digits, 7, 2) === '05';
    }

    private static function digits(string $processNumber): ?string
    {
        $digits = preg_replace('/\D/', '', $processNumber) ?? '';

        return strlen($digits) === 23 ? $digits : null;
    }
}

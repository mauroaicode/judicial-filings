<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

/**
 * Construye el nombre de despacho (court) a partir de metadatos SAMAI.
 *
 * Preferencia: campo visual "Origen" del portal/API. Fallback: Ponente cuando
 * es un juzgado/tribunal, o NombreSalaDecision (+ ciudad).
 */
final class SamaiCourtNameHelper
{
    /**
     * @param  array<string, mixed>  $processData
     */
    public static function build(array $processData): string
    {
        $origen = self::origenName($processData);
        if ($origen !== '') {
            return $origen;
        }

        $ponente = trim((string) ($processData['Ponente'] ?? ''));
        if (self::isCourtName($ponente)) {
            return self::scrubSamaiTypo($ponente);
        }

        $seccion = trim((string) ($processData['NombreSalaDecision'] ?? $processData['Seccion'] ?? ''));
        $city = trim((string) ($processData['cityName'] ?? ''));

        if ($seccion === '') {
            return '';
        }

        if ($city !== '' && ! str_contains(mb_strtolower($seccion), mb_strtolower($city))) {
            return "{$seccion} - {$city}";
        }

        return $seccion;
    }

    /**
     * @param  array<string, mixed>  $processData
     */
    private static function origenName(array $processData): string
    {
        foreach (['Origen', 'OrigenNombre', 'EntidadRadicadoraNombre'] as $key) {
            $raw = trim((string) ($processData[$key] ?? ''));
            if ($raw !== '') {
                return self::normalizeOrigen($raw);
            }
        }

        $entidad = trim((string) ($processData['EntidadRadicadora'] ?? ''));
        // En REST a veces llega el código numérico del despacho, no el nombre.
        if ($entidad === '' || preg_match('/^\d+$/', $entidad) === 1) {
            return '';
        }

        return self::normalizeOrigen($entidad);
    }

    /**
     * Limpia prefijos tipo "Juzgado Administrativo 014 JUZGADO ADMINISTRATIVO...".
     */
    private static function normalizeOrigen(string $origen): string
    {
        $origen = trim((string) preg_replace('/\s+/u', ' ', $origen));

        if (preg_match_all('/\b(?:JUZGADO|TRIBUNAL)\b/iu', $origen, $matches, PREG_OFFSET_CAPTURE) > 0) {
            $last = end($matches[0]);
            $offset = is_array($last) ? (int) $last[1] : 0;
            $origen = trim(mb_substr($origen, $offset));
        }

        return self::scrubSamaiTypo($origen);
    }

    private static function scrubSamaiTypo(string $value): string
    {
        // Typo frecuente en SAMAI: "de CaliI"
        return (string) preg_replace('/\bCaliI\b/u', 'Cali', $value);
    }

    private static function isCourtName(string $value): bool
    {
        $normalized = mb_strtolower(trim($value));

        return str_starts_with($normalized, 'juzgado')
            || str_starts_with($normalized, 'tribunal');
    }
}

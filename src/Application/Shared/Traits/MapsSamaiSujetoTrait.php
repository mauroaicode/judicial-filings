<?php

declare(strict_types=1);

namespace Src\Application\Shared\Traits;

/**
 * Mapea un sujeto procesal de la API REST de SAMAI a los atributos de ProcessSubject.
 *
 * La API SAMAI puede devolver distintas variantes de nombres de campo:
 *  TipoSujeto / tipoSujeto / Tipo        → subject_type
 *  NombreRazonSocial / Nombre / nombre   → name_or_business_name
 *  Identificacion / identificacion       → identification
 *  EsEmplazado / esEmplazado             → is_cited
 */
trait MapsSamaiSujetoTrait
{
    /**
     * Mapea un sujeto de SAMAI a atributos para ProcessSubject (sin process_id).
     *
     * @param  array<string, mixed>  $apiSujeto
     * @return array<string, mixed>
     */
    protected function mapSamaiSujetoToAttributes(array $apiSujeto): array
    {
        $tipoRaw = (string) $this->firstSamaiField($apiSujeto, ['TipoSujeto', 'tipoSujeto', 'Tipo', 'tipo', 'TipoParte', 'tipoParte'], '');
        $nombre = (string) $this->firstSamaiField($apiSujeto, ['NombreRazonSocial', 'nombreRazonSocial', 'Nombre', 'nombre', 'NombreCompleto', 'nombreCompleto'], '');
        $identificacion = $this->firstSamaiField($apiSujeto, ['Identificacion', 'identificacion', 'NumeroIdentificacion', 'numeroIdentificacion']);
        $esEmplazado = (bool) $this->firstSamaiField($apiSujeto, ['EsEmplazado', 'esEmplazado'], false);

        return [
            'subject_registration_id' => null,
            'subject_type' => $this->normalizeSamaiSubjectType($tipoRaw),
            'is_cited' => $esEmplazado,
            'identification' => $identificacion !== null ? (string) $identificacion : null,
            'name_or_business_name' => $nombre,
        ];
    }

    /**
     * Normaliza el tipo de sujeto SAMAI al formato estándar del sistema (Demandante / Demandado).
     */
    protected function normalizeSamaiSubjectType(string $tipo): string
    {
        $t = trim($tipo);

        if (stripos($t, 'Demandante') !== false || stripos($t, 'Accionante') !== false) {
            return 'Demandante';
        }

        if (stripos($t, 'Demandado') !== false || stripos($t, 'Accionado') !== false) {
            return 'Demandado';
        }

        return $t !== '' ? $t : 'Otro';
    }

    /**
     * Busca el primer campo disponible en un array de posibles claves.
     *
     * @param  list<string>  $keys
     */
    private function firstSamaiField(array $payload, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null && $payload[$key] !== '') {
                return $payload[$key];
            }
        }

        return $default;
    }
}

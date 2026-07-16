<?php

declare(strict_types=1);

namespace Src\Application\Shared\Traits;

use Illuminate\Support\Facades\Date;

/**
 * Mapea una actuación de la API REST de SAMAI a los atributos de ProcessAction.
 *
 * Campos de la API SAMAI:
 *  A110LLAVPROC   → radicado (process_number)
 *  Orden          → cons_action + action_registration_id (identificador incremental único)
 *  NombreActuacion → action
 *  Actuacion      → action_date (string, puede ser "YYYY-MM-DD" o "DD/MM/YYYY")
 *  Anotacion      → annotation
 *  Registro       → registration_date
 *  CodiActuacion  → no mapeado (no existe campo equivalente en ProcessAction)
 *  Estado         → no mapeado
 */
trait MapsSamaiActuacionTrait
{
    /**
     * Mapea un ítem del array de actuaciones de SAMAI a atributos para ProcessAction (sin process_id).
     *
     * @param  array<string, mixed>  $apiActuacion
     * @return array<string, mixed>
     */
    protected function mapSamaiActuacionToAttributes(array $apiActuacion): array
    {
        $orden = (int) ($apiActuacion['Orden'] ?? 0);

        $actionDate = $this->parseSamaiDate($apiActuacion['Actuacion'] ?? null)
            ?? now()->format('Y-m-d');

        $registrationDate = $this->parseSamaiDate($apiActuacion['Registro'] ?? null)
            ?? $actionDate;

        return [
            'action_registration_id' => $orden,
            'cons_action' => $orden,
            'action_date' => $actionDate,
            'action' => (string) ($apiActuacion['NombreActuacion'] ?? ''),
            'annotation' => isset($apiActuacion['Anotacion']) && $apiActuacion['Anotacion'] !== ''
                ? (string) $apiActuacion['Anotacion']
                : null,
            'start_date' => null,
            'end_date' => null,
            'registration_date' => $registrationDate,
        ];
    }

    /**
     * Extrae el Orden (identificador) de una actuación SAMAI.
     */
    protected function samaiActuacionOrden(array $apiActuacion): int
    {
        return (int) ($apiActuacion['Orden'] ?? 0);
    }

    /**
     * Normaliza fechas de SAMAI que pueden llegar en distintos formatos.
     * Soporta: "YYYY-MM-DD", "DD/MM/YYYY", "YYYY-MM-DDTHH:mm:ss", timestamps ISO.
     */
    private function parseSamaiDate(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $str = trim((string) $date);

        if ($str === '' || strtolower($str) === 'null') {
            return null;
        }

        // Formato DD/MM/YYYY
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $str, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        try {
            return Date::parse($str)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}

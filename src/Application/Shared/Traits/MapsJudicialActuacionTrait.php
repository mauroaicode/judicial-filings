<?php

declare(strict_types=1);

namespace Src\Application\Shared\Traits;

use Illuminate\Support\Facades\Date;
use Stringable;

trait MapsJudicialActuacionTrait
{
    /**
     * Map API actuación array to ProcessAction-compatible attributes (excluding process_id).
     *
     * @param  array<string, mixed>  $apiActuacion  Raw actuación from Judicial Branch API
     * @return array<string, mixed> Attributes for ProcessAction fillable
     */
    protected function mapApiActuacionToAttributes(array $apiActuacion): array
    {
        $registrationRaw = $this->firstRawJudicialDateFromPayload($apiActuacion, [
            'fechaRegistro',
            'fecha_registro',
            'FechaRegistro',
        ]);

        $actionRaw = $this->firstRawJudicialDateFromPayload($apiActuacion, [
            'fechaActuacion',
            'fecha_actuacion',
            'FechaActuacion',
        ]);

        $registrationDate = $this->parseActuacionDate($registrationRaw)
            ?? now()->format('Y-m-d');

        $actionDate = $this->parseActuacionDate($actionRaw)
            ?? $registrationDate;

        return [
            'action_registration_id' => (int) ($apiActuacion['idRegActuacion'] ?? 0),
            'cons_action' => (int) ($apiActuacion['consActuacion'] ?? 0),
            'action_date' => $actionDate,
            'action' => (string) ($apiActuacion['actuacion'] ?? ''),
            'annotation' => isset($apiActuacion['anotacion']) ? (string) $apiActuacion['anotacion'] : null,
            'start_date' => $this->parseActuacionDate(
                $this->firstRawJudicialDateFromPayload($apiActuacion, ['fechaInicial', 'fecha_inicial', 'FechaInicial'])
            ),
            'end_date' => $this->parseActuacionDate(
                $this->firstRawJudicialDateFromPayload($apiActuacion, ['fechaFinal', 'fecha_final', 'FechaFinal'])
            ),
            'registration_date' => $registrationDate,
        ];
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function firstRawJudicialDateFromPayload(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if ($value === null) {
                continue;
            }

            if ($value === '') {
                continue;
            }

            if (is_scalar($value) || $value instanceof Stringable) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Normalize Portal Judicial date payloads (strings, unix timestamps, JSON.NET "/Date(ms)/").
     */
    private function parseActuacionDate(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        if ($date instanceof Stringable) {
            $date = (string) $date;
        }

        if (is_int($date) || is_float($date)) {
            return $this->parseEpochLikeNumber((float) $date);
        }

        if (! is_string($date)) {
            return null;
        }

        $trimmed = trim($date);

        if ($trimmed === '' || strcasecmp($trimmed, 'null') === 0) {
            return null;
        }

        if (preg_match('#^/Date\\((\\d+)#', $trimmed, $matches)) {
            return $this->parseEpochLikeNumber((float) $matches[1]);
        }

        try {
            return Date::parse($trimmed)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseEpochLikeNumber(float $value): ?string
    {
        $seconds = $value > 1e12
            ? (int) ($value / 1000)
            : (int) $value;

        if ($seconds < 1) {
            return null;
        }

        try {
            return Date::createFromTimestamp($seconds)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}

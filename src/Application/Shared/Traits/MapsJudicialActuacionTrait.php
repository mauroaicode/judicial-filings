<?php

declare(strict_types=1);

namespace Src\Application\Shared\Traits;

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
        return [
            'action_registration_id' => (int) ($apiActuacion['idRegActuacion'] ?? 0),
            'action_date' => $this->parseActuacionDate($apiActuacion['fechaActuacion'] ?? null),
            'action' => (string) ($apiActuacion['actuacion'] ?? ''),
            'annotation' => isset($apiActuacion['anotacion']) ? (string) $apiActuacion['anotacion'] : null,
            'start_date' => $this->parseActuacionDate($apiActuacion['fechaInicial'] ?? null),
            'end_date' => $this->parseActuacionDate($apiActuacion['fechaFinal'] ?? null),
            'registration_date' => $this->parseActuacionDate($apiActuacion['fechaRegistro'] ?? null) ?? now()->format('Y-m-d'),
        ];
    }

    /**
     * Parse date string from API to Y-m-d. No I/O, no logs.
     */
    private function parseActuacionDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            return \Illuminate\Support\Facades\Date::parse($date)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}

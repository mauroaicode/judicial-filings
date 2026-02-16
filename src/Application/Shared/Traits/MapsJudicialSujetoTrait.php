<?php

declare(strict_types=1);

namespace Src\Application\Shared\Traits;

trait MapsJudicialSujetoTrait
{
    /**
     * Map API sujeto array to ProcessSubject-compatible attributes (excluding process_id).
     *
     * @param  array<string, mixed>  $apiSujeto  Raw sujeto from Judicial Branch API
     * @return array<string, mixed> Attributes for ProcessSubject fillable
     */
    protected function mapApiSujetoToAttributes(array $apiSujeto): array
    {
        $tipoSujeto = (string) ($apiSujeto['tipoSujeto'] ?? '');

        return [
            'subject_registration_id' => (int) ($apiSujeto['idRegSujeto'] ?? 0),
            'subject_type' => $this->normalizeSubjectType($tipoSujeto),
            'is_cited' => (bool) ($apiSujeto['esEmplazado'] ?? false),
            'identification' => isset($apiSujeto['identificacion']) ? (string) $apiSujeto['identificacion'] : null,
            'name_or_business_name' => (string) ($apiSujeto['nombreRazonSocial'] ?? ''),
        ];
    }

    /**
     * Normaliza tipoSujeto de la API (ej. "Demandante/accionante") a "Demandante" o "Demandado"
     * para que coincida con lo que usa ProcessIndexResource y el listado.
     */
    protected function normalizeSubjectType(string $tipoSujeto): string
    {
        $t = trim($tipoSujeto);
        if (stripos($t, 'Demandante') !== false) {
            return 'Demandante';
        }

        if (stripos($t, 'Demandado') !== false) {
            return 'Demandado';
        }

        return $tipoSujeto;
    }
}

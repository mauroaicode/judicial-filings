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
        return [
            'subject_registration_id' => (int) ($apiSujeto['idRegSujeto'] ?? 0),
            'subject_type' => (string) ($apiSujeto['tipoSujeto'] ?? ''),
            'is_cited' => (bool) ($apiSujeto['esEmplazado'] ?? false),
            'identification' => isset($apiSujeto['identificacion']) ? (string) $apiSujeto['identificacion'] : null,
            'name_or_business_name' => (string) ($apiSujeto['nombreRazonSocial'] ?? ''),
        ];
    }
}

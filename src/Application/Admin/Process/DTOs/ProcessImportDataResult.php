<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\DTOs;

readonly class ProcessImportDataResult
{
    /**
     * @param  int  $status  HTTP status (200, 202, 422).
     * @param  array<string, mixed>  $body  Response body (message, errors?, batch_id?, skipped_already_registered?).
     * @param  array<int, string>|null  $toEnqueue  Radicados a encolar (solo si status sería 202).
     * @param  string|null  $organizationId  Organization UUID.
     * @param  string|null  $fileName  Nombre del archivo.
     * @param  int  $skippedAlreadyRegistered  Cuántos ya estaban registrados.
     * @param  string|int|null  $requestedById  User id que solicitó la importación.
     * @param  string  $source  Fuente de los procesos: 'judicial_branch' | 'samai'.
     * @param  array<int, array{process_number: string, reason: string}>  $initialErrors  Errores previos (p. ej. cupo).
     * @param  bool  $requiresQuotaFailureBatch  Crear lote fallido + notificar cuando todo fue rechazado por cupo.
     */
    public function __construct(
        public int $status,
        public array $body,
        public ?array $toEnqueue = null,
        public ?string $organizationId = null,
        public ?string $fileName = null,
        public int $skippedAlreadyRegistered = 0,
        public mixed $requestedById = null,
        public string $source = 'judicial_branch',
        public array $initialErrors = [],
        public bool $requiresQuotaFailureBatch = false,
    ) {}

    public function isReadyToEnqueue(): bool
    {
        return $this->toEnqueue !== null && $this->toEnqueue !== [] && $this->organizationId !== null;
    }

    public function quotaRejectedCount(): int
    {
        return count($this->initialErrors);
    }
}

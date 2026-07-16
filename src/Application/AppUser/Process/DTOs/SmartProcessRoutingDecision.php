<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\DTOs;

use Src\Domain\Process\Enums\ProcessDataSourceSlug;

/**
 * Resultado de la detección automática de fuente para un radicado.
 *
 * El resolver determina:
 *  - qué fuente de datos tiene el proceso (Rama Judicial, SAMAI, …),
 *  - si el alta debe ir a cola (historial largo) o ejecutarse inline,
 *  - opcionalmente, la respuesta de la API de Rama Judicial ya obtenida
 *    para evitar una segunda llamada en el path inline.
 */
readonly class SmartProcessRoutingDecision
{
    /**
     * @param  array<int, array<string, mixed>>|null  $prefetchedJbProcesses  Respuesta de fetchProcesses() ya obtenida;
     *                                                                        se pasa a RegisterProcessService para evitar doble llamada.
     */
    public function __construct(
        public ProcessDataSourceSlug $source,
        public bool $deferToQueue,
        public ?array $prefetchedJbProcesses = null,
    ) {}
}

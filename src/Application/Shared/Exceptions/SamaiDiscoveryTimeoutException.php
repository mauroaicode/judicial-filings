<?php

declare(strict_types=1);

namespace Src\Application\Shared\Exceptions;

use RuntimeException;

/**
 * La búsqueda de corporación en SAMAI agotó el tiempo de espera en todos los candidatos.
 *
 * Esto NO significa que el proceso no exista — solo que el servidor de SAMAI tardó
 * más de lo permitido. El caller debe reintentar más tarde (p. ej. mandando a cola).
 *
 * Diferencia clave respecto a un resultado vacío ([]):
 *  - []                            → el proceso definitivamente no está en SAMAI.
 *  - SamaiDiscoveryTimeoutException → SAMAI estaba lento; vale la pena reintentar.
 */
class SamaiDiscoveryTimeoutException extends RuntimeException
{
    public function __construct(
        public readonly string $processNumber,
    ) {
        parent::__construct(
            "SAMAI discovery timeout for radicado {$processNumber}: todos los candidatos excedieron el tiempo de espera."
        );
    }
}

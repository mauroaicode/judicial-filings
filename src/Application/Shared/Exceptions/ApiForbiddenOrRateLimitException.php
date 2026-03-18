<?php

declare(strict_types=1);

namespace Src\Application\Shared\Exceptions;

use Exception;

/**
 * La API de la Rama Judicial respondió 403 Forbidden o 429 Too Many Requests.
 * Transitorio: el job de importación debe reintentar.
 *
 * Cuando la respuesta incluye el header Retry-After, se almacena en $retryAfter
 * para que el job pueda priorizar ese valor exacto en el backoff.
 */
class ApiForbiddenOrRateLimitException extends Exception
{
    public function __construct(
        string $message = '',
        /** Valor del header Retry-After en segundos (null si no vino en la respuesta). */
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }
}

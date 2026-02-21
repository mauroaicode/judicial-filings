<?php

declare(strict_types=1);

namespace Src\Application\Shared\Exceptions;

use Exception;

/**
 * La API de la Rama Judicial respondió 200 pero con procesos vacíos.
 * Fallo definitivo: no se reintenta.
 */
class ApiEmptyProcessesException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}

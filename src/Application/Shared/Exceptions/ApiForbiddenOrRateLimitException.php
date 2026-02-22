<?php

declare(strict_types=1);

namespace Src\Application\Shared\Exceptions;

use Exception;

/**
 * La API de la Rama Judicial respondió 403 Forbidden o 429 Too Many Requests.
 * Transitorio: el job de importación debe reintentar.
 */
class ApiForbiddenOrRateLimitException extends Exception {}

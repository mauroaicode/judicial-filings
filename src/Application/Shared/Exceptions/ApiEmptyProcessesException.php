<?php

declare(strict_types=1);

namespace Src\Application\Shared\Exceptions;

use Exception;

/**
 * La API de la Rama Judicial respondió HTTP 200 pero con array de procesos vacío.
 *
 * Rama Judicial puede devolver vacío de forma transitoria bajo carga (falso vacío),
 * por lo que el job reintenta un número limitado de veces antes de marcar fallo definitivo.
 * Si tras todos los reintentos sigue vacío, el radicado genuinamente no existe.
 */
class ApiEmptyProcessesException extends Exception {}

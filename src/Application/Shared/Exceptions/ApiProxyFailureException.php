<?php

declare(strict_types=1);

namespace Src\Application\Shared\Exceptions;

use Exception;

/**
 * El proxy seleccionado no pudo conectar con la API del Portal Judicial.
 *
 * Causas habituales:
 *   - cURL error 7:  el proxy está caído o bloqueado (Failed to connect).
 *   - cURL error 28: el proxy agotó el tiempo de espera (Connection timed out).
 *
 * El job de importación debe reintentar inmediatamente; en el siguiente intento
 * se seleccionará una IP diferente del pool (array_rand).
 */
class ApiProxyFailureException extends Exception {}

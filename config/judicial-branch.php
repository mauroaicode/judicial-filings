<?php

return [
    'api_url' => env('JUDICIAL_BRANCH_API_URL'),
    'timeout_seconds' => (int) env('JUDICIAL_BRANCH_TIMEOUT_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Rate limit de llamadas HTTP individuales
    |--------------------------------------------------------------------------
    |
    | Límite de peticiones HTTP individuales por minuto hacia la API de la
    | Rama Judicial. Cada llamada cuenta: búsqueda, detalle, actuaciones y
    | sujetos. El throttle ahora bloquea (sleep) en lugar de lanzar excepción,
    | por lo que este valor controla directamente el ritmo de procesamiento:
    |   - 8 calls/min → 2 radicados/min → 138 radicados en ~69 min
    |   - 4 calls/min → 1 radicado/min  → 138 radicados en ~138 min
    | Subir si la API lo permite; bajar si aparecen 403 en el log.
    |
    */
    'rate_limit_per_minute' => (int) env('JUDICIAL_BRANCH_RATE_LIMIT_PER_MINUTE', 8),
];

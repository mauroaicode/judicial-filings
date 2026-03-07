<?php

return [
    'api_url' => env('JUDICIAL_BRANCH_API_URL'),
    'timeout_seconds' => (int) env('JUDICIAL_BRANCH_TIMEOUT_SECONDS', 60),

    'log_channel' => env('JUDICIAL_BRANCH_LOG_CHANNEL', 'process_import'),

    /*
    |--------------------------------------------------------------------------
    | Rate limit global de llamadas HTTP (sin proxy)
    |--------------------------------------------------------------------------
    |
    | Usa Laravel RateLimiter para limitar llamadas cuando el proxy está
    | deshabilitado (todas las llamadas salen desde la misma IP del servidor).
    |   8 calls/min → ~2 radicados/min (4 calls por radicado)
    |
    */
    'rate_limit_per_minute' => (int) env('JUDICIAL_BRANCH_RATE_LIMIT_PER_MINUTE', 8),

    /*
    |--------------------------------------------------------------------------
    | Pool de Proxies (Webshare Proxy Server — Direct Connection)
    |--------------------------------------------------------------------------
    |
    | Cuando está habilitado, cada request a Rama Judicial usa una IP diferente
    | del pool de 250 IPs de Webshare. La selección es seed-based (round-robin
    | sin locks): cada radicado usa su número como seed para mapear a una IP
    | distinta, distribuyendo la carga naturalmente entre workers.
    |
    | Configuración en Webshare:
    |   - Authentication Method: IP Authentication (sin usuario/contraseña)
    |   - Connection Method: Direct Connection (cada IP tiene su propio puerto)
    |
    | Variables .env requeridas:
    |   JUDICIAL_BRANCH_PROXY_ENABLED=true
    |   JUDICIAL_BRANCH_PROXY_WEBSHARE_API_KEY=tu-api-key
    |   JUDICIAL_BRANCH_PROXY_WEBSHARE_AUTH_MODE=ip   (o "credentials")
    |
    | Flujo:
    |   1. php artisan proxy:refresh  → carga las 250 IPs en la BD
    |   2. Cada job llama next(seed)  → obtiene una IP por posición
    |   3. cURL 7/28/56              → markFailed() desactiva esa IP
    |   4. HTTP 403                  → reintento con diferente seed/IP
    |
    */
    'proxy' => [
        'enabled' => (bool) env('JUDICIAL_BRANCH_PROXY_ENABLED', false),
        'webshare_api_key' => env('JUDICIAL_BRANCH_PROXY_WEBSHARE_API_KEY', ''),
        'webshare_auth_mode' => env('JUDICIAL_BRANCH_PROXY_WEBSHARE_AUTH_MODE', 'ip'),
        'timeout' => (int) env('JUDICIAL_BRANCH_PROXY_TIMEOUT', 20),

        /*
         * Milliseconds to sleep between every HTTP call to Rama Judicial when
         * proxy is active. Applies per worker independently (no shared lock).
         *
         * 500 ms  → safe, ~3 radicados/min with 3 workers
         * 200 ms  → faster, ~7 radicados/min with 3 workers
         * 0       → no delay (not recommended with multiple workers)
         */
        'call_delay_ms' => (int) env('JUDICIAL_BRANCH_PROXY_CALL_DELAY_MS', 500),
    ],
];

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
    | Proxy Residencial Rotativo (Webshare Rotating Residential)
    |--------------------------------------------------------------------------
    |
    | Un único endpoint rotativo. Webshare asigna automáticamente una IP
    | residencial colombiana diferente en cada nueva conexión TCP.
    | No se necesita pool en BD ni comando proxy:refresh.
    |
    | Configuración en Webshare:
    |   - Producto: Rotating Residential
    |   - Authentication Method: Username/Password
    |   - Connection Method: Rotating Proxy Endpoint
    |   - Country filter: Colombia (CO)
    |
    | Variables .env requeridas:
    |   JUDICIAL_BRANCH_PROXY_ENABLED=true
    |   JUDICIAL_BRANCH_PROXY_HOST=p.webshare.io
    |   JUDICIAL_BRANCH_PROXY_PORT=80
    |   JUDICIAL_BRANCH_PROXY_USERNAME=wfvehrrcresidential-CO-rotate
    |   JUDICIAL_BRANCH_PROXY_PASSWORD=ab7xwhoq3eip
    |
    */
    'proxy' => [
        'enabled'  => (bool) env('JUDICIAL_BRANCH_PROXY_ENABLED', false),
        'host'     => env('JUDICIAL_BRANCH_PROXY_HOST', 'p.webshare.io'),
        'port'     => (int) env('JUDICIAL_BRANCH_PROXY_PORT', 80),
        'username' => env('JUDICIAL_BRANCH_PROXY_USERNAME', ''),
        'password' => env('JUDICIAL_BRANCH_PROXY_PASSWORD', ''),
        'timeout'  => (int) env('JUDICIAL_BRANCH_PROXY_TIMEOUT', 20),

        /*
         * Jitter aleatorio entre peticiones a Rama Judicial.
         * Emula el tiempo de lectura/clic de un humano para evitar
         * la detección de patrones robóticos por Cloudflare WAF.
         *
         * Rango recomendado: 1500–3500 ms (~2.5 s promedio)
         *   → ~4 peticiones / 10 s por radicado
         *   → ~6 radicados / minuto por worker
         */
        'call_delay_min_ms' => (int) env('JUDICIAL_BRANCH_PROXY_CALL_DELAY_MIN_MS', 1500),
        'call_delay_max_ms' => (int) env('JUDICIAL_BRANCH_PROXY_CALL_DELAY_MAX_MS', 3500),
    ],
];

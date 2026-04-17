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
    | Proxy residencial rotativo (ProxyScrape)
    |--------------------------------------------------------------------------
    |
    | Variables .env requeridas:
    |   JUDICIAL_BRANCH_PROXY_ENABLED=true
    |   JUDICIAL_BRANCH_PROXY_PROTOCOL=http
    |   JUDICIAL_BRANCH_PROXY_HOST=rp.scrapegw.com
    |   JUDICIAL_BRANCH_PROXY_PORT=6060
    |   JUDICIAL_BRANCH_PROXY_USERNAME=...
    |   JUDICIAL_BRANCH_PROXY_PASSWORD=...
    |
    */
    'proxy' => [
        'provider' => env('JUDICIAL_BRANCH_PROXY_PROVIDER', 'proxyscrape'),
        'enabled' => (bool) env('JUDICIAL_BRANCH_PROXY_ENABLED', false),
        'protocol' => env('JUDICIAL_BRANCH_PROXY_PROTOCOL', 'http'),
        'host' => env('JUDICIAL_BRANCH_PROXY_HOST', 'rp.scrapegw.com'),
        'port' => (int) env('JUDICIAL_BRANCH_PROXY_PORT', 6060),
        'username' => env('JUDICIAL_BRANCH_PROXY_USERNAME', ''),
        'password' => env('JUDICIAL_BRANCH_PROXY_PASSWORD', ''),
        'timeout' => (int) env('JUDICIAL_BRANCH_PROXY_TIMEOUT', 30),
        'enable_session_mutation' => (bool) env('JUDICIAL_BRANCH_PROXY_ENABLE_SESSION_MUTATION', true),
        'max_connection_retries' => (int) env('JUDICIAL_BRANCH_PROXY_MAX_CONNECTION_RETRIES', 2),
        'connection_retry_base_ms' => (int) env('JUDICIAL_BRANCH_PROXY_CONNECTION_RETRY_BASE_MS', 700),
        'connection_circuit_breaker_ms' => (int) env('JUDICIAL_BRANCH_PROXY_CONNECTION_CIRCUIT_BREAKER_MS', 3000),

        /*
         * Jitter aleatorio entre peticiones al Portal Judicial.
         * Emula el tiempo de lectura/clic de un humano para evitar
         * la detección de patrones robóticos por Cloudflare WAF.
         *
         * Rango solicitado: 2500–5500 ms (2.5s - 5.5s)
         */
        'call_delay_min_ms' => (int) env('JUDICIAL_BRANCH_PROXY_CALL_DELAY_MIN_MS', 2500),
        'call_delay_max_ms' => (int) env('JUDICIAL_BRANCH_PROXY_CALL_DELAY_MAX_MS', 5500),
    ],
];

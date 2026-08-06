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
    | HTTPS en puerto 448: si CONNECT HTTP falla (cURL 56), usar SOCKS5 según el dashboard.
    |
    | Variables .env habituales:
    |   JUDICIAL_BRANCH_PROXY_ENABLED=true
    |   JUDICIAL_BRANCH_PROXY_PROTOCOL=http (o socks5)
    |   JUDICIAL_BRANCH_PROXY_HOST=rp.scrapegw.com
    |   JUDICIAL_BRANCH_PROXY_PORT=6060 (HTTP) u otro para SOCKS5
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

    /*
    |--------------------------------------------------------------------------
    | Registro AppUser: inline vs cola según volumen de actuaciones
    |--------------------------------------------------------------------------
    |
    | Si alguna instancia nueva del radicado tiene más de este número de páginas
    | de actuaciones en el Portal, el alta va en cola (SyncJudicialBranchJob).
    | Con cantidadPaginas <= este valor, el registro termina en la misma petición HTTP.
    |
    | Si hay un sync diario activo (batch pending), el alta también se encola aunque
    | el historial sea corto, para no pelear locks con el cron (deadlocks MySQL).
    |
    */
    'registration_inline_max_actuacion_pages' => (int) env('JUDICIAL_BRANCH_REGISTRATION_INLINE_MAX_ACTUACION_PAGES', 2),

    /*
    |--------------------------------------------------------------------------
    | Reintentos de transacción DB al registrar (deadlock 1213)
    |--------------------------------------------------------------------------
    */
    'registration_db_transaction_attempts' => (int) env('JUDICIAL_BRANCH_REGISTRATION_DB_TRANSACTION_ATTEMPTS', 5),
];

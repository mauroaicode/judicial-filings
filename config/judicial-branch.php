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
    | Proxy Residencial Rotativo (ProxyScrape / Webshare)
    |--------------------------------------------------------------------------
    |
    | Variables .env requeridas:
    |   JUDICIAL_BRANCH_PROXY_ENABLED=true
    |   JUDICIAL_BRANCH_PROXY_PROTOCOL=socks5h (recomendado para puerto 448)
    |   JUDICIAL_BRANCH_PROXY_HOST=rp.scrapegw.com
    |   JUDICIAL_BRANCH_PROXY_PORT=6060
    |   JUDICIAL_BRANCH_PROXY_USERNAME=...
    |   JUDICIAL_BRANCH_PROXY_PASSWORD=...
    |
    */
    'proxy' => [
        'enabled'  => (bool) env('JUDICIAL_BRANCH_PROXY_ENABLED', false),
        'protocol' => env('JUDICIAL_BRANCH_PROXY_PROTOCOL', 'socks5h'),
        'host'     => env('JUDICIAL_BRANCH_PROXY_HOST', 'rp.scrapegw.com'),
        'port'     => (int) env('JUDICIAL_BRANCH_PROXY_PORT', 6060),
        'username' => env('JUDICIAL_BRANCH_PROXY_USERNAME', ''),
        'password' => env('JUDICIAL_BRANCH_PROXY_PASSWORD', ''),
        'timeout'  => (int) env('JUDICIAL_BRANCH_PROXY_TIMEOUT', 30),

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

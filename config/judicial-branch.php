<?php

return [
    'api_url' => env('JUDICIAL_BRANCH_API_URL'),
    'timeout_seconds' => (int) env('JUDICIAL_BRANCH_TIMEOUT_SECONDS', 60),

    /*
     * Timeout HTTP cuando hay proxy activo. Los proxies datacenter suelen
     * fallar en ~10 s (cURL 28), así que 15 s es suficiente para no bloquear
     * el worker más tiempo del necesario antes de reintentar con otra IP.
     */
    'proxy_timeout_seconds' => (int) env('JUDICIAL_BRANCH_PROXY_TIMEOUT_SECONDS', 15),

    'log_channel' => env('JUDICIAL_BRANCH_LOG_CHANNEL', 'process_import'),

    /*
    |--------------------------------------------------------------------------
    | Rate limit de llamadas HTTP individuales (sin proxies)
    |--------------------------------------------------------------------------
    |
    | Máximo de peticiones HTTP por minuto hacia la API de Rama Judicial.
    | Cada radicado genera 4 llamadas: búsqueda, detalle, actuaciones, sujetos.
    |
    |   8 calls/min  → ~2 radicados/min → 138 radicados ≈ 69 min
    |   16 calls/min → ~4 radicados/min → 138 radicados ≈ 35 min
    |
    | Este throttle se IGNORA cuando proxy.enabled = true, ya que cada
    | request sale desde una IP diferente y el rate limit por IP no aplica.
    |
    */
    'rate_limit_per_minute' => (int) env('JUDICIAL_BRANCH_RATE_LIMIT_PER_MINUTE', 8),

    /*
    |--------------------------------------------------------------------------
    | Proxy Pool — rotación de IPs para importación masiva
    |--------------------------------------------------------------------------
    |
    | Cuando está habilitado, cada request HTTP a Rama Judicial sale desde
    | una IP del pool en rotación secuencial (round-robin), eliminando el
    | rate limit por IP. El índice rotativo se guarda en Cache de forma
    | atómica (compatible con Redis, Memcached y database driver).
    |
    | ACTIVAR:
    |   JUDICIAL_BRANCH_PROXY_ENABLED=true
    |   JUDICIAL_BRANCH_PROXY_PROVIDER=webshare   (recomendado)
    |   PROCESS_IMPORT_DELAY_SECONDS=3
    |
    | PROVEEDORES disponibles:
    |
    |   webshare    → Proveedor recomendado. API REST con autenticación Bearer.
    |                 Plan gratuito: 10 proxies. Desde $5/mes: 100+ proxies.
    |                 KEY: JUDICIAL_BRANCH_PROXY_WEBSHARE_API_KEY
    |                 URL: JUDICIAL_BRANCH_PROXY_WEBSHARE_URL (opcional)
    |
    |   proxyscrape → Proxies HTTP de datacenter. Respuesta: texto plano ip:port
    |                 por línea. Soportan CONNECT tunneling (HTTPS puerto 448).
    |                 URL: JUDICIAL_BRANCH_PROXY_PROXYSCRAPE_URL
    |
    |   geonode     → Proxies HTTP gratuitos. Respuesta: JSON con array "data",
    |                 cada item tiene "ip" y "port".
    |                 URL: JUDICIAL_BRANCH_PROXY_GEONODE_URL
    |
    | El pool se cachea JUDICIAL_BRANCH_PROXY_CACHE_TTL_MINUTES minutos.
    |
    */
    'proxy' => [
        'enabled' => (bool) env('JUDICIAL_BRANCH_PROXY_ENABLED', false),

        'provider' => env('JUDICIAL_BRANCH_PROXY_PROVIDER', 'proxyscrape'),

        // --- Webshare (recomendado) ---
        'webshare_api_key' => env('JUDICIAL_BRANCH_PROXY_WEBSHARE_API_KEY'),
        'webshare_url' => env(
            'JUDICIAL_BRANCH_PROXY_WEBSHARE_URL',
            'https://proxy.webshare.io/api/v2/proxy/list/?mode=direct&page=1&page_size=100'
        ),

        // --- ProxyScrape ---
        'proxyscrape_url' => env(
            'JUDICIAL_BRANCH_PROXY_PROXYSCRAPE_URL',
            'https://api.proxyscrape.com/v2/account/datacenter_shared/proxy-list?auth=nd5mj9mbo93ezor333qi&type=getproxies&country[]=all&protocol=http&format=normal&status=all'
        ),

        // --- Geonode ---
        'geonode_url' => env(
            'JUDICIAL_BRANCH_PROXY_GEONODE_URL',
            'https://proxylist.geonode.com/api/proxy-list?protocols=http&limit=500&page=1&sort_by=lastChecked&sort_type=desc'
        ),

        'cache_ttl_minutes' => (int) env('JUDICIAL_BRANCH_PROXY_CACHE_TTL_MINUTES', 60),
    ],
];

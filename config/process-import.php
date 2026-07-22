<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | Log channel for process import (errors and start/end of each radicado).
    |
    */
    'log_channel' => env('PROCESS_IMPORT_LOG_CHANNEL', 'process_import'),

    /*
    |--------------------------------------------------------------------------
    | Admin report notification email
    |--------------------------------------------------------------------------
    |
    | Email address of the system administrator that always receives the import
    | report (success/failed counts and list of failed radicados with reason).
    | Set in .env as PROCESS_IMPORT_REPORT_EMAIL.
    |
    */
    'admin_report_email' => env('ADMIN_PROCESS_IMPORT_REPORT_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Queue and delay
    |--------------------------------------------------------------------------
    */
    'delay_between_radicados_seconds' => (int) env('PROCESS_IMPORT_DELAY_SECONDS', 15),

    /*
    |--------------------------------------------------------------------------
    | API rate limit and retry (evitar bloqueo Portal Judicial)
    |--------------------------------------------------------------------------
    |
    | rate_limit_per_minute: máx. consultas a la API por minuto (recomendado 4-6).
    | retry_release_seconds: segundos de espera antes de reintentar si falla.
    | retry_max_attempts: cuántas veces reintentar (otros errores) antes de marcar fallido.
    | retry_max_attempts_for_not_found: reintentos extra para "no existe en el Portal Judicial"
    |   (puede ser transitorio: rate limit, timeout, fallo API). Ej. 10 = hasta 11 intentos.
    | retry_release_seconds_for_not_found: espera antes de reintentar "not found" (ej. 300 = 5 min).
    |
    */
    'rate_limit_per_minute' => (int) env('PROCESS_IMPORT_RATE_LIMIT_PER_MINUTE', 4),
    'retry_release_seconds' => (int) env('PROCESS_IMPORT_RETRY_RELEASE_SECONDS', 120),
    'retry_max_attempts' => (int) env('PROCESS_IMPORT_RETRY_MAX_ATTEMPTS', 2),
    'retry_max_attempts_for_not_found' => (int) env('PROCESS_IMPORT_RETRY_MAX_ATTEMPTS_NOT_FOUND', 10),
    'retry_release_seconds_for_not_found' => (int) env('PROCESS_IMPORT_RETRY_RELEASE_SECONDS_NOT_FOUND', 300),

    /*
    |--------------------------------------------------------------------------
    | Retry para timeout de discovery SAMAI
    |--------------------------------------------------------------------------
    |
    | Sin ApiKey, buscarProceso cae a ObtenerDatosProcesoGet con 3 candidatos.
    | En juzgados departamentales esa API puede tardar >25s. El job reintenta
    | con más margen; el cliente también cae al portal HTML como refuerzo.
    |
    */
    'retry_max_attempts_for_samai_discovery_timeout' => (int) env('PROCESS_IMPORT_RETRY_MAX_ATTEMPTS_SAMAI_DISCOVERY_TIMEOUT', 5),
    'retry_release_seconds_for_samai_discovery_timeout' => (int) env('PROCESS_IMPORT_RETRY_RELEASE_SECONDS_SAMAI_DISCOVERY_TIMEOUT', 180),

    /*
    |--------------------------------------------------------------------------
    | Retry para respuesta vacía (200 pero sin procesos)
    |--------------------------------------------------------------------------
    |
    | El Portal Judicial puede devolver HTTP 200 con array vacío de forma transitoria
    | cuando está bajo carga (comportamiento observado en logs). Un reintento
    | corto resuelve el problema. Máximo 3 intentos con 120s de espera.
    | Si tras todos los reintentos sigue vacío → fallo definitivo (radicado
    | genuinamente no existe en el Portal Judicial).
    |
    */
    'retry_max_attempts_for_empty' => (int) env('PROCESS_IMPORT_RETRY_MAX_ATTEMPTS_EMPTY', 3),
    'retry_release_seconds_for_empty' => (int) env('PROCESS_IMPORT_RETRY_RELEASE_SECONDS_EMPTY', 120),

    /*
    |--------------------------------------------------------------------------
    | Retry para 403/429 del Portal Judicial
    |--------------------------------------------------------------------------
    |
    | Con proxy rotatorio: el 403 significa que esa IP egress está bloqueada,
    | pero Webshare asigna una IP nueva en el siguiente intento. Reintento
    | casi inmediato (5s), igual que los errores de proxy cURL.
    |
    | Sin proxy: 403 = rate limit real de la IP del servidor. Esperar más tiempo.
    | Se aplica jitter del 20% para distribuir los reintentos simultáneos.
    |
    */
    'retry_release_seconds_for_rate_limit' => (int) env('PROCESS_IMPORT_RETRY_RELEASE_RATE_LIMIT', 180),
    'retry_release_seconds_for_rate_limit_proxy' => (int) env('PROCESS_IMPORT_RETRY_RELEASE_RATE_LIMIT_PROXY', 5),
    'retry_max_attempts_for_rate_limit' => (int) env('PROCESS_IMPORT_RETRY_MAX_ATTEMPTS_RATE_LIMIT', 10),

    /*
    |--------------------------------------------------------------------------
    | Retry para fallo de proxy (cURL error 7 / cURL error 28)
    |--------------------------------------------------------------------------
    |
    | Solo aplica cuando JUDICIAL_BRANCH_PROXY_ENABLED=true y el proxy
    | seleccionado no pudo conectar (cURL 7 = proxy caído) o agotó el tiempo
    | de espera (cURL 28 = proxy lento/bloqueado).
    |
    | El reintento es casi inmediato (5 s) porque en el siguiente intento
    | array_rand() seleccionará una IP diferente del pool de 1000 proxies.
    | Con 10 reintentos máx, el job prueba hasta 10 IPs distintas antes de
    | marcarse como fallido.
    |
    */
    'retry_release_seconds_for_proxy_failure' => (int) env('PROCESS_IMPORT_RETRY_RELEASE_PROXY_FAILURE', 5),
    'retry_max_attempts_for_proxy_failure' => (int) env('PROCESS_IMPORT_RETRY_MAX_ATTEMPTS_PROXY_FAILURE', 10),

    /*
    |--------------------------------------------------------------------------
    | Report channels
    |--------------------------------------------------------------------------
    |
    | Channels used to send the report when import batch completes.
    | Each channel has its own job/queue. Add 'discord' when configured.
    |
    */
    'report_channels' => array_filter(explode(',', env('PROCESS_IMPORT_REPORT_CHANNELS', 'email'))),

    /*
    |--------------------------------------------------------------------------
    | Job configuration
    |--------------------------------------------------------------------------
    */
    'jobs' => [
        'import_radicado' => [
            'queue' => env('PROCESS_IMPORT_QUEUE') ?: 'process-import',
            'tries' => (int) env('PROCESS_IMPORT_TRIES', 30),
            'timeout' => (int) env('PROCESS_IMPORT_JOB_TIMEOUT', 600),
            'connection' => env('PROCESS_IMPORT_QUEUE_CONNECTION'),
        ],
        'send_report' => [
            'queue' => env('PROCESS_IMPORT_REPORT_QUEUE') ?: 'process-import-report',
            'tries' => (int) env('PROCESS_IMPORT_REPORT_TRIES', 3),
            'timeout' => (int) env('PROCESS_IMPORT_REPORT_TIMEOUT', 60),
            'connection' => env('PROCESS_IMPORT_QUEUE_CONNECTION'),
        ],
    ],
];

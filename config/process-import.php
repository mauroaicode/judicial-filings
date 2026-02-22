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
    | API rate limit and retry (evitar bloqueo Rama Judicial)
    |--------------------------------------------------------------------------
    |
    | rate_limit_per_minute: máx. consultas a la API por minuto (recomendado 4-6).
    | retry_release_seconds: segundos de espera antes de reintentar si falla.
    | retry_max_attempts: cuántas veces reintentar (otros errores) antes de marcar fallido.
    | retry_max_attempts_for_not_found: reintentos extra para "no existe en Rama Judicial"
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
    | Retry para respuesta vacía (200 pero sin procesos)
    |--------------------------------------------------------------------------
    |
    | Rama Judicial puede devolver HTTP 200 con array vacío de forma transitoria
    | cuando está bajo carga (comportamiento observado en logs). Un reintento
    | corto resuelve el problema. Máximo 3 intentos con 120s de espera.
    | Si tras todos los reintentos sigue vacío → fallo definitivo (radicado
    | genuinamente no existe en Rama Judicial).
    |
    */
    'retry_max_attempts_for_empty' => (int) env('PROCESS_IMPORT_RETRY_MAX_ATTEMPTS_EMPTY', 3),
    'retry_release_seconds_for_empty' => (int) env('PROCESS_IMPORT_RETRY_RELEASE_SECONDS_EMPTY', 120),

    /*
    |--------------------------------------------------------------------------
    | Retry para rate limit real de la API (403/429 HTTP)
    |--------------------------------------------------------------------------
    |
    | Solo aplica cuando la API de Rama Judicial devuelve un 403 o 429 real.
    | El throttle interno ya NO lanza excepción: bloquea el job con sleep hasta
    | que el cupo se libere (máx 60s). Por tanto, este retry es solo para
    | cuando el servidor externo rechaza la petición directamente.
    | El log muestra que la API tarda 3-5 min en recuperarse; se aplica jitter
    | del 20% (ej. 180 → espera 180-216s).
    |
    */
    'retry_release_seconds_for_rate_limit' => (int) env('PROCESS_IMPORT_RETRY_RELEASE_RATE_LIMIT', 180),

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

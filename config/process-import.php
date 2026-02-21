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
    | Report notification email
    |--------------------------------------------------------------------------
    |
    | Email address that receives the import report (success/failed counts and
    | list of failed radicados with reason). Set in .env as PROCESS_IMPORT_REPORT_EMAIL.
    |
    */
    'report_email' => env('PROCESS_IMPORT_REPORT_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Queue and delay
    |--------------------------------------------------------------------------
    */
    'queue' => env('PROCESS_IMPORT_QUEUE') ?: 'process-import',
    'delay_between_radicados_seconds' => (int) env('PROCESS_IMPORT_DELAY_SECONDS', 5),

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
    'rate_limit_per_minute' => (int) env('PROCESS_IMPORT_RATE_LIMIT_PER_MINUTE', 6),
    'retry_release_seconds' => (int) env('PROCESS_IMPORT_RETRY_RELEASE_SECONDS', 120),
    'retry_max_attempts' => (int) env('PROCESS_IMPORT_RETRY_MAX_ATTEMPTS', 2),
    'retry_max_attempts_for_not_found' => (int) env('PROCESS_IMPORT_RETRY_MAX_ATTEMPTS_NOT_FOUND', 10),
    'retry_release_seconds_for_not_found' => (int) env('PROCESS_IMPORT_RETRY_RELEASE_SECONDS_NOT_FOUND', 300),

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
            // Para 500-1000 radicados con rate limit bajo, usar 120-200 para tolerar muchos release(60).
            'tries' => (int) env('PROCESS_IMPORT_TRIES', 120),
            'timeout' => (int) env('PROCESS_IMPORT_JOB_TIMEOUT', 120),
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

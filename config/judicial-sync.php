<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | Name of the log channel used for judicial sync and notifications.
    | Must match a channel defined in config/logging.php.
    |
    */
    'log_channel' => env('JUDICIAL_SYNC_LOG_CHANNEL', 'judicial_sync_notifications'),

    /*
    |--------------------------------------------------------------------------
    | Job Configuration
    |--------------------------------------------------------------------------
    |
    | Queue, retries, backoff and timeout for sync and notification jobs.
    |
    */
    'jobs' => [
        'sync_process' => [
            'queue' => env('JUDICIAL_SYNC_QUEUE', 'judicial-sync'),
            'tries' => (int) env('JUDICIAL_SYNC_TRIES', 3),
            'backoff' => (int) env('JUDICIAL_SYNC_BACKOFF', 60),
            'timeout' => (int) env('JUDICIAL_SYNC_TIMEOUT', 120),
            'connection' => env('JUDICIAL_SYNC_CONNECTION'),
        ],
        'sync_samai_process' => [
            'queue' => env('SAMAI_SYNC_QUEUE', 'samai-sync'),
            'tries' => (int) env('SAMAI_SYNC_TRIES', 3),
            'backoff' => (int) env('SAMAI_SYNC_BACKOFF', 60),
            'timeout' => (int) env('SAMAI_SYNC_TIMEOUT', 120),
            'connection' => env('SAMAI_SYNC_CONNECTION'),
        ],
        'migrate_private_source' => [
            'queue' => env('MIGRATE_PRIVATE_SOURCE_QUEUE', 'judicial-sync'),
            'tries' => (int) env('MIGRATE_PRIVATE_SOURCE_TRIES', 2),
            'timeout' => (int) env('MIGRATE_PRIVATE_SOURCE_TIMEOUT', 120),
            'connection' => env('MIGRATE_PRIVATE_SOURCE_CONNECTION'),
        ],
        'send_notification_dispatcher' => [
            'queue' => env('JUDICIAL_NOTIFICATION_QUEUE', 'notifications'),
            'tries' => (int) env('JUDICIAL_NOTIFICATION_TRIES', 3),
            'backoff' => (int) env('JUDICIAL_NOTIFICATION_BACKOFF', 30),
            'timeout' => (int) env('JUDICIAL_NOTIFICATION_TIMEOUT', 60),
            'connection' => env('JUDICIAL_NOTIFICATION_CONNECTION'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Granular Queues per Channel Type
    |--------------------------------------------------------------------------
    |
    | Define specific queues for each delivery channel to separate traffic.
    |
    */
    'queues' => [
        'internal' => env('NOTIFICATION_INTERNAL_QUEUE', 'notifications'),
        'email' => env('NOTIFICATION_EMAIL_QUEUE', 'notifications-email'),
        'sms' => env('NOTIFICATION_SMS_QUEUE', 'notifications-sms'),
        'whatsapp' => env('NOTIFICATION_WHATSAPP_QUEUE', 'notifications-whatsapp'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inactive Process Skip Threshold
    |--------------------------------------------------------------------------
    |
    | Number of days without activity after which fetchActionByProcess is skipped
    | in the daily sync. A process whose last_activity_date is older than this
    | threshold will only run fetchProcesses (to detect new instances) and skip
    | the actuaciones check — saving one proxy request per inactive process.
    |
    | With two daily crons (9am and 3:30pm), a threshold of 2 days ensures that
    | any process active in the last 48 hours is always fully checked, preventing
    | missed same-day actions between cron runs.
    |
    | Set to 0 to disable the optimization and always fetch actuaciones.
    |
    */
    'inactive_skip_threshold_days' => (int) env('JUDICIAL_SYNC_INACTIVE_SKIP_DAYS', 2),

    /*
    |--------------------------------------------------------------------------
    | New Instance Notification Window (days)
    |--------------------------------------------------------------------------
    |
    | When a new process instance is discovered during the daily sync (i.e. it
    | has no existing actuaciones yet), the system fetches its full historical
    | record. To avoid flooding clients with old notifications, only actuaciones
    | whose action_date falls within this many days in the past will trigger
    | notifications.
    |
    | If sibling instances for the same radicado already have synced actuaciones,
    | their max last_activity_date is used as the cutoff instead (whichever is
    | more recent), since that represents the "known state" of the radicado.
    |
    | Historical actuaciones are still stored for full traceability and AI/LLM
    | training; this setting only controls what gets notified.
    |
    | Set to 0 to use only the sibling-instance cutoff (no fixed-window fallback).
    |
    */
    'new_instance_notify_days' => (int) env('JUDICIAL_SYNC_NEW_INSTANCE_NOTIFY_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Registration Alert Window (days forward)
    |--------------------------------------------------------------------------
    |
    | When a process is newly registered (manual or bulk import), the system
    | saves the full historical record but only queues digest notifications for
    | actuaciones whose action_date OR registration_date falls between today
    | and today + N days (inclusive). Default 1 = today and tomorrow.
    |
    */
    'registration_alert_days_forward' => (int) env('JUDICIAL_SYNC_REGISTRATION_ALERT_DAYS_FORWARD', 1),

    /*
    |--------------------------------------------------------------------------
    | Duplicate folder / phantom instance handling
    |--------------------------------------------------------------------------
    |
    | Rama Judicial sometimes returns several idProceso rows for the same
    | radicado (same despacho, empty sujetos). Those folders often replay the
    | same actuaciones with different idRegActuacion values.
    |
    */
    'skip_phantom_instance_actuaciones' => (bool) env('JUDICIAL_SYNC_SKIP_PHANTOM_INSTANCES', true),

    'dedupe_actions_by_content' => (bool) env('JUDICIAL_SYNC_DEDUPE_ACTIONS_BY_CONTENT', true),

    /*
    |--------------------------------------------------------------------------
    | Reintentos de migración: procesos privados en JB sin migrar a SAMAI
    |--------------------------------------------------------------------------
    |
    | Cuando un proceso pasa a privado en Rama Judicial, se intenta migrarlo a SAMAI
    | de inmediato. Si falla (SAMAI aún no lo tiene), este mecanismo de backoff por
    | niveles permite reintentar progresivamente sin sobrecargar la API.
    |
    | Nivel 1: 1-3 días después del flip  → primer reintento
    | Nivel 2: 3-7 días                   → segundo reintento
    | Nivel 3: 7-14 días                  → tercer reintento (último)
    | Give-up: >14 días                   → alerta al operador, no se reintenta más
    |
    | Recomendación: correr judicial:retry-private-migrations UNA VEZ AL DÍA.
    |
    */
    'private_migration_retry_level1_days' => (int) env('PRIVATE_MIGRATION_RETRY_LEVEL1_DAYS', 1),
    'private_migration_retry_level2_days' => (int) env('PRIVATE_MIGRATION_RETRY_LEVEL2_DAYS', 3),
    'private_migration_retry_level3_days' => (int) env('PRIVATE_MIGRATION_RETRY_LEVEL3_DAYS', 7),
    /*
    |--------------------------------------------------------------------------
    | Admin alert: proceso pasó a privado
    |--------------------------------------------------------------------------
    |
    | Correo interno para el administrador cuando un proceso de Rama Judicial
    | se marca como privado. Las organizaciones NO reciben este aviso: siguen
    | el proceso normalmente (migración a SAMAI + consolidado de actuaciones).
    |
    */
    'admin_privacy_transition_email' => env(
        'ADMIN_PRIVACY_TRANSITION_EMAIL',
        env('ADMIN_PROCESS_IMPORT_REPORT_EMAIL')
    ),
];

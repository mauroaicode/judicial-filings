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
    | Discovered-today digest bypass (max age in days)
    |--------------------------------------------------------------------------
    |
    | Rama sometimes publishes an actuación today with a slightly old
    | registration_date. Those still enter the consolidado if first saved today.
    |
    | Age is measured from registration_date. Actuaciones older than this many
    | days (e.g. a year-old PUBLICACIÓN ESTADO backfill) are stored but not
    | notified. Set to 0 to disable the bypass entirely (strict cutoff only).
    |
    */
    'discovered_today_max_age_days' => (int) env('JUDICIAL_SYNC_DISCOVERED_TODAY_MAX_AGE_DAYS', 30),

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

    /*
    |--------------------------------------------------------------------------
    | Auto Digest After Sync
    |--------------------------------------------------------------------------
    |
    | When true (default), each sync batch (judicial:sync-processes and
    | samai:sync-processes) automatically fires DispatchOrganizationDigestsJob
    | at the end of the batch — sending the consolidated email immediately.
    |
    | Set to false when you want to accumulate notifications from multiple
    | sources (Rama Judicial, SAMAI, manual Excel uploads) and send a single
    | consolidated email via the admin "Enviar consolidado" button
    | (POST /api/admin/digest-packages/send).
    |
    | With this flag off the crons still store every new actuacion as pending
    | (is_email_notified = false); nothing is lost. The next digest dispatch
    | (manual or automatic) will include all accumulated pending items.
    |
    */
    'auto_digest_after_sync' => (bool) env('JUDICIAL_SYNC_AUTO_DIGEST', true),

    /*
    |--------------------------------------------------------------------------
    | Rama data-replication staleness (fecha de replicación)
    |--------------------------------------------------------------------------
    |
    | During judicial:sync-processes, each radicado's detail endpoint is checked
    | for ultimaActualizacion (portal: "Fecha de replicación de datos") vs
    | fechaConsulta. When the lag exceeds stale_after_hours, the radicado is
    | queued for a Discord alert on CHANNEL_LATE_SYNC after the batch ends.
    |
    | When exclude_weekends is true, only Monday–Friday hours count toward the
    | lag (so a Friday→Monday gap does not look like ~70 calendar hours).
    |
    | When exclude_colombia_holidays is true, Colombian public holidays (via
    | rmunate/calendario-colombia) are also excluded from the lag.
    |
    | Costs one extra detail API call per radicado that reaches actuacion sync.
    |
    */
    'replication_staleness' => [
        'enabled' => (bool) env('JUDICIAL_SYNC_REPLICATION_STALENESS_ENABLED', true),
        'stale_after_hours' => (int) env('JUDICIAL_SYNC_REPLICATION_STALE_HOURS', 24),
        'exclude_weekends' => (bool) env('JUDICIAL_SYNC_REPLICATION_STALE_EXCLUDE_WEEKENDS', true),
        'exclude_colombia_holidays' => (bool) env('JUDICIAL_SYNC_REPLICATION_STALE_EXCLUDE_HOLIDAYS', true),
    ],
];

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
];

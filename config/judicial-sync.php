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
        'email'    => env('NOTIFICATION_EMAIL_QUEUE', 'notifications-email'),
        'sms'      => env('NOTIFICATION_SMS_QUEUE', 'notifications-sms'),
        'whatsapp' => env('NOTIFICATION_WHATSAPP_QUEUE', 'notifications-whatsapp'),
    ],
];

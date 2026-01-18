<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue supports a variety of backends via a single, unified
    | API, giving you convenient access to each backend using identical
    | syntax for each. The default queue connection is defined below.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every queue backend
    | used by your application. An example configuration is provided for
    | each backend supported by Laravel. You're also free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_QUEUE_HOST', 'localhost'),
            'queue' => env('BEANSTALKD_QUEUE', 'default'),
            'retry_after' => (int) env('BEANSTALKD_QUEUE_RETRY_AFTER', 90),
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control how and where failed jobs are stored. Laravel ships with
    | support for storing failed jobs in a simple file or in a database.
    |
    | Supported drivers: "database-uuids", "dynamodb", "file", "null"
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Queue Names
    |--------------------------------------------------------------------------
    |
    | Define custom queue names for specific job types to better organize
    | and prioritize job processing.
    |
    */

    'queues' => [
        'default',
        // Queue responsible for initiating the judicial process sync workflow
        // after command execution. Internally starts the flow to obtain filing
        // numbers, process them, AI verification, and all subsequent steps.
        'process-sync' => [
            'channel' => 'database',
            'queue' => 'process-sync',
        ],
        // Job queue for processing filing numbers in chunks and verifying
        // them against the judicial branch API
        'process-chunk' => [
            'channel' => 'database',
            'queue' => 'process-chunk',
        ],
        'process-action-sync' => [
            'channel' => 'database',
            'queue' => 'process-action-sync',
        ],
        // Notification channels - each with its own queue for better management
        'notifications' => [
            'channel' => 'database',
            'queue' => 'notifications',
            'delay_for_organization' => 30,
        ],
        // Email notifications queue
        'notifications-email' => [
            'channel' => 'database',
            'queue' => 'notifications-email',
            'delay_for_organization' => 60, // 60 segundos para evitar rate limiting de email
            'max_attempts' => 5,
            'retry_after' => 300, // 5 minutos
            'timeout' => 120, // 2 minutos timeout
        ],
        // WhatsApp notifications queue
        'notifications-whatsapp' => [
            'channel' => 'database',
            'queue' => 'notifications-whatsapp',
            'delay_for_organization' => 30, // 30 segundos para WhatsApp
            'max_attempts' => 3,
            'retry_after' => 180, // 3 minutos
            'timeout' => 60, // 1 minuto timeout
        ],
        // SMS notifications queue
        'notifications-sms' => [
            'channel' => 'database',
            'queue' => 'notifications-sms',
            'delay_for_organization' => 45, // 45 segundos para SMS
            'max_attempts' => 3,
            'retry_after' => 180, // 3 minutos
            'timeout' => 60, // 1 minuto timeout
        ],
        // Internal notifications queue (dashboard, mobile app)
        'notifications-internal' => [
            'channel' => 'database',
            'queue' => 'notifications-internal',
            'delay_for_organization' => 10, // 10 segundos para notificaciones internas
            'max_attempts' => 2,
            'retry_after' => 60, // 1 minuto
            'timeout' => 30, // 30 segundos timeout
        ],
    ],

];

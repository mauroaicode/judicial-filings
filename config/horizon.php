<?php

use Illuminate\Support\Str;

return [

    'name' => env('HORIZON_NAME', 'NotiJudicial'),

    /*
    | Comma-separated emails allowed when a user is logged in via web session.
    */
    'allowed_emails' => env('HORIZON_ALLOWED_EMAILS', ''),

    /*
    | Secret token for /horizon?token=... (required in production for API-only apps).
    | Generate with: php artisan tinker --execute="echo bin2hex(random_bytes(32));"
    */
    'secret' => env('HORIZON_SECRET', ''),

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    'middleware' => [
        'web',
        \App\Http\Middleware\PersistInternalToolToken::class,
    ],

    'waits' => [
        'redis:default' => 60,
        'redis:judicial-sync' => 300,
        'redis:process-import' => 120,
        'redis:notifications' => 60,
    ],

    'trim' => [
        'recent' => 120,   // 2 horas
        'pending' => 120,
        'completed' => 120,
        'recent_failed' => 10080, // 7 días
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 48,
            'queue' => 48,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 128,

    /*
    |--------------------------------------------------------------------------
    | Definición de supervisores por entorno
    |--------------------------------------------------------------------------
    |
    | Cada supervisor equivale a un grupo de workers en un queue específico.
    | Los valores en "defaults" se usan como plantilla; "environments" permite
    | sobreescribir por entorno (production / local).
    |
    */

    'defaults' => [
        'supervisor-judicial-sync' => [
            'connection' => 'redis',
            'queue' => ['judicial-sync'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 2,
            'maxProcesses' => 8,
            'maxTime' => 0,
            'maxJobs' => 500,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 0,
        ],

        'supervisor-process-import' => [
            'connection' => 'redis',
            'queue' => ['process-import'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 5,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 120,
            'timeout' => 300,
            'nice' => 0,
        ],

        'supervisor-notifications' => [
            'connection' => 'redis',
            'queue' => ['notifications', 'notifications-email', 'notifications-whatsapp', 'notifications-sms'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 4,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],

        'supervisor-misc' => [
            'connection' => 'redis',
            'queue' => ['emails_account', 'emails_import_report', 'discord-notifications'],
            'balance' => 'simple',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-judicial-sync' => [
                'minProcesses' => 4,
                'maxProcesses' => 8,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 5,
            ],
            'supervisor-process-import' => [
                'minProcesses' => 2,
                'maxProcesses' => 5,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 5,
            ],
            'supervisor-notifications' => [
                'minProcesses' => 1,
                'maxProcesses' => 4,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-misc' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'local' => [
            'supervisor-judicial-sync' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
            'supervisor-process-import' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
            'supervisor-notifications' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
            'supervisor-misc' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
        ],
    ],

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];

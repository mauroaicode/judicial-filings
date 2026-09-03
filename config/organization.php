<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Organization defaults
    |--------------------------------------------------------------------------
    |
    | Used when organization_settings.max_active_processes is null / missing.
    | Set ORGANIZATION_DEFAULT_MAX_ACTIVE_PROCESSES in .env (e.g. 50).
    | Leave empty/null for unlimited when not configured per org.
    |
    */
    'defaults' => [
        'max_active_processes' => env('ORGANIZATION_DEFAULT_MAX_ACTIVE_PROCESSES') !== null
            && env('ORGANIZATION_DEFAULT_MAX_ACTIVE_PROCESSES') !== ''
            ? (int) env('ORGANIZATION_DEFAULT_MAX_ACTIVE_PROCESSES')
            : null,
    ],
];

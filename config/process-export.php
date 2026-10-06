<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Dedicated queue so exports never share workers with judicial-sync / samai-sync.
    |
    */
    'queue' => env('PROCESS_EXPORT_QUEUE', 'exports'),

    'tries' => (int) env('PROCESS_EXPORT_TRIES', 5),

    'timeout' => (int) env('PROCESS_EXPORT_TIMEOUT', 300),

    /** Longer timeout when the workbook includes the Actuaciones sheet. */
    'timeout_with_actions' => (int) env('PROCESS_EXPORT_TIMEOUT_WITH_ACTIONS', 900),

    /*
    |--------------------------------------------------------------------------
    | Defer while daily sync is writing
    |--------------------------------------------------------------------------
    |
    | When JudicialSyncRun::hasActiveBatch() is true, the export job releases
    | itself back to the queue instead of reading heavy joins under load.
    |
    */
    'defer_while_sync_active' => (bool) env('PROCESS_EXPORT_DEFER_WHILE_SYNC', true),

    'sync_defer_seconds' => (int) env('PROCESS_EXPORT_SYNC_DEFER_SECONDS', 60),

    'sync_defer_max_releases' => (int) env('PROCESS_EXPORT_SYNC_DEFER_MAX_RELEASES', 60),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */
    'disk' => env('PROCESS_EXPORT_DISK', 'local'),

    'directory' => env('PROCESS_EXPORT_DIRECTORY', 'exports/processes'),

    /** Hours until the generated file is considered expired for download. */
    'ttl_hours' => (int) env('PROCESS_EXPORT_TTL_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Query chunk / safety limits
    |--------------------------------------------------------------------------
    */
    'chunk_size' => (int) env('PROCESS_EXPORT_CHUNK_SIZE', 100),

    /** Hard cap for actuaciones rows in a single workbook (fail fast with a clear error). */
    'max_action_rows' => (int) env('PROCESS_EXPORT_MAX_ACTION_ROWS', 100000),

    /*
    |--------------------------------------------------------------------------
    | Concurrency lock (Redis / cache)
    |--------------------------------------------------------------------------
    |
    | Only one export generation per organization at a time.
    |
    */
    'org_lock_seconds' => (int) env('PROCESS_EXPORT_ORG_LOCK_SECONDS', 600),
];

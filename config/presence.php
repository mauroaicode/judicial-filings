<?php

return [

    /*
    | Queue consumed by Horizon (supervisor-presence). Leave the connection
    | empty to use QUEUE_CONNECTION. Set PRESENCE_QUEUE_CONNECTION=redis when
    | the default queue is database and Horizon is the worker.
    */
    'queue' => env('PRESENCE_QUEUE', 'presence'),

    'connection' => env('PRESENCE_QUEUE_CONNECTION'),

    /*
    | Debounce store. Redis keeps the "already seen today" lock off MySQL.
    | Tests override this with the array store.
    */
    'cache_store' => env('PRESENCE_CACHE_STORE', 'redis'),

    'debounce_minutes' => (int) env('PRESENCE_DEBOUNCE_MINUTES', 15),

    'retention_months' => (int) env('PRESENCE_RETENTION_MONTHS', 12),

    'channel' => 'app-users.online',

    /*
    | ipwho.is returns country, region and city without an API key.
    | The lookup runs in the queue, only when the IP changes, and is cached.
    */
    'geolocation_url' => env('PRESENCE_GEOLOCATION_URL', 'https://ipwho.is'),

    'geolocation_timeout' => (int) env('PRESENCE_GEOLOCATION_TIMEOUT', 3),

];

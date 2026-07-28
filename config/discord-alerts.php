<?php

return [
    /*
     * The webhook URLs that we'll use to send a message to Discord.
     */
    'webhook_urls' => [
        'default' => env('DISCORD_ALERT_WEBHOOK'),
        /** #log-sync-daily — judicial sync command (`judicial:sync-processes`) completion reports. */
        'log_sync_daily' => env('DISCORD_ALERT_WEBHOOK_LOG_SYNC_DAILY'),
        /** #sincronizacion-tardia — Rama data-replication lag (fecha de replicación vs consulta). */
        'late_sync' => env('DISCORD_ALERT_WEBHOOK_LATE_SYNC'),
    ],

    /*
     * Default avatar is an empty string '' which means it will not be included in the payload.
     * You can add multiple custom avatars and then specify directly with withAvatar()
     */
    'avatar_urls' => [
        'default' => '',
    ],

    /*
     * This job will send the message to Discord. You can extend this
     * job to set timeouts, retries, etc...
     */
    'job' => Spatie\DiscordAlerts\Jobs\SendToDiscordChannelJob::class,

    /*
    * The queue name that should be used to send the alert. Only supported for drivers
    * that allow multiple queues (e.g., redis, database, beanstalkd). Ignored for sync and null drivers.
    */
    'queue' => env('DISCORD_ALERT_QUEUE', 'default'),
];

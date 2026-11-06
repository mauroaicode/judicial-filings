<?php

declare(strict_types=1);

$defaultNotificationChannelLimit = env('ORGANIZATION_DEFAULT_MAX_NOTIFICATION_CHANNELS', 3);
$emailVerificationTtl = env('NOTIFICATION_CHANNEL_EMAIL_VERIFICATION_TTL_HOURS', 72);

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
    | notification_channel_limit applies to each configurable channel
    | (email, WhatsApp, SMS) when the organization has no row in
    | organization_notification_channel_limits.
    |
    */
    'defaults' => [
        'max_active_processes' => env('ORGANIZATION_DEFAULT_MAX_ACTIVE_PROCESSES') !== null
            && env('ORGANIZATION_DEFAULT_MAX_ACTIVE_PROCESSES') !== ''
            ? (int) env('ORGANIZATION_DEFAULT_MAX_ACTIVE_PROCESSES')
            : null,
        'notification_channel_limit' => ($defaultNotificationChannelLimit === null || $defaultNotificationChannelLimit === '')
            ? 3
            : max(0, (int) $defaultNotificationChannelLimit),
    ],

    /*
    | How long the notification-email confirmation link stays valid.
    */
    'notification_channel_email_verification_ttl_hours' => ($emailVerificationTtl === null || $emailVerificationTtl === '')
        ? 72
        : max(1, (int) $emailVerificationTtl),
];

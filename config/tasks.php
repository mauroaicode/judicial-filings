<?php

declare(strict_types=1);

return [

    'log_channel' => env('TASK_URGENCY_LOG_CHANNEL', 'stack'),

    /*
    | Days since task creation (pending, not completed) for urgency alerts.
    | normal < alert_1 < alert_2 < critical
    */
    'urgency_thresholds' => [
        'alert_1' => (int) env('TASK_URGENCY_ALERT_1_DAYS', 10),
        'alert_2' => (int) env('TASK_URGENCY_ALERT_2_DAYS', 15),
        'critical' => (int) env('TASK_URGENCY_CRITICAL_DAYS', 30),
    ],

    'frontend' => [
        'base_url' => env('FRONTEND_URL', 'http://localhost:4200'),
        'tasks_path' => env('FRONTEND_TASKS_PATH', '/tareas'),
    ],

    'queues' => [
        'email' => env('TASK_URGENCY_EMAIL_QUEUE', 'notifications-email'),
        'internal' => env('TASK_URGENCY_INTERNAL_QUEUE', 'notifications'),
        'sms' => env('TASK_URGENCY_SMS_QUEUE', 'notifications'),
        'whatsapp' => env('TASK_URGENCY_WHATSAPP_QUEUE', 'notifications'),
    ],

];

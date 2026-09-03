<?php

declare(strict_types=1);

return [
    'event_types' => [
        'process_became_private' => 'The process became private',
        'process_source_changed' => 'The judicial source changed',
        'task_created' => 'A task was created',
        'task_updated' => 'A task was updated',
        'task_status_changed' => 'A task status changed',
        'task_deleted' => 'A task was deleted',
        'task_restored' => 'A task was restored',
        'process_suspended' => 'The process was suspended',
        'process_resumed' => 'The process was resumed',
        'tracking_activated' => 'Tracking was enabled',
        'tracking_deactivated' => 'Tracking was disabled',
        'tracking_trashed' => 'The process was moved to trash',
        'semaphore_changed' => 'The semaphore changed',
        'speaker_changed' => 'The reporting judge changed',
        'unknown' => 'Process update',
    ],

    'reasons' => [
        'current_state_backfill' => 'Initial status recorded',
        'became_private_at_backfill' => 'Privacy change recorded',
        'current_source_backfill' => 'Initial judicial source recorded',
        'current_status_backfill' => 'Initial task status recorded',
        'judicial_branch_api_reported_private' => 'The court reported the process as private in the official consultation systems',
        'suspension_task_created_or_updated' => 'Suspension task',
        'suspension_task_completed' => 'The suspension task was completed',
        'suspension_task_deleted' => 'The suspension task was deleted',
        'suspension_ended' => 'The suspension ended',
        'new_judicial_action' => 'A new judicial action was recorded',
        'inactividad_roja' => 'Critical inactivity',
        'inactividad_amarilla' => 'Inactivity warning',
        'inactividad_verde' => 'Favorable inactivity',
        'actividad_roja' => 'Recent activity requires attention',
        'actividad_amarilla' => 'Moderate activity',
        'unknown' => 'Process update',
    ],

    'colors' => [
        'red' => 'Red',
        'yellow' => 'Yellow',
        'green' => 'Green',
        'none' => 'No previous level',
    ],

    'sources' => [
        'judicial_branch' => 'Official consultation systems',
        'samai' => 'SAMAI',
        'user' => 'User',
        'system' => 'System',
        'backfill' => 'System',
    ],

    'actors' => [
        'app_user' => 'User',
        'admin' => 'Administrator',
        'job' => 'Automatic update',
        'system' => 'System',
        'unknown' => 'System',
    ],

    'summaries' => [
        'semaphore_changed' => 'The semaphore changed from :from to :to.',
        'task_status_changed' => 'The status changed from :from to :to.',
    ],

    'date_labels' => [
        'semaphore_recorded_at' => 'Semaphore date',
        'action_date' => 'Action date',
        'registration_date' => 'Registration date',
        'last_activity_date' => 'Last activity date',
        'speaker_changed_at' => 'Reporting judge change date',
        'unknown' => 'Date',
    ],

    'values' => [
        'not_available' => 'No information',
    ],
];

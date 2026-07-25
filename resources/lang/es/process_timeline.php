<?php

declare(strict_types=1);

return [
    'event_types' => [
        'process_became_private' => 'El proceso pasó a privado',
        'process_source_changed' => 'Cambió la fuente judicial',
        'task_created' => 'Se creó una tarea',
        'task_updated' => 'Se actualizó una tarea',
        'task_status_changed' => 'Cambió el estado de una tarea',
        'task_deleted' => 'Se eliminó una tarea',
        'task_restored' => 'Se restauró una tarea',
        'process_suspended' => 'El proceso fue suspendido',
        'process_resumed' => 'El proceso fue reanudado',
        'tracking_activated' => 'Se activó el seguimiento',
        'tracking_deactivated' => 'Se desactivó el seguimiento',
        'semaphore_changed' => 'Cambió el semáforo',
        'speaker_changed' => 'Cambió el ponente',
        'unknown' => 'Actualización del proceso',
    ],

    'reasons' => [
        'current_state_backfill' => 'Estado inicial registrado',
        'became_private_at_backfill' => 'Cambio de privacidad registrado',
        'current_source_backfill' => 'Fuente judicial inicial registrada',
        'current_status_backfill' => 'Estado inicial de la tarea registrado',
        'judicial_branch_api_reported_private' => 'La Rama Judicial reportó el proceso como privado',
        'suspension_task_created_or_updated' => 'Tarea de suspensión',
        'suspension_task_completed' => 'La tarea de suspensión fue completada',
        'suspension_task_deleted' => 'La tarea de suspensión fue eliminada',
        'suspension_ended' => 'Finalizó la suspensión',
        'new_judicial_action' => 'Se registró una nueva actuación judicial',
        'inactividad_roja' => 'Inactividad crítica',
        'inactividad_amarilla' => 'Alerta de inactividad',
        'inactividad_verde' => 'Inactividad favorable',
        'actividad_roja' => 'Actividad reciente que requiere atención',
        'actividad_amarilla' => 'Actividad moderada',
        'unknown' => 'Actualización del proceso',
    ],

    'colors' => [
        'red' => 'Rojo',
        'yellow' => 'Amarillo',
        'green' => 'Verde',
        'none' => 'Sin nivel anterior',
    ],

    'sources' => [
        'judicial_branch' => 'Rama Judicial',
        'samai' => 'SAMAI',
        'user' => 'Usuario',
        'system' => 'Sistema',
        'backfill' => 'Sistema',
    ],

    'actors' => [
        'app_user' => 'Usuario',
        'admin' => 'Administrador',
        'job' => 'Actualización automática',
        'system' => 'Sistema',
        'unknown' => 'Sistema',
    ],

    'summaries' => [
        'semaphore_changed' => 'El semáforo cambió de :from a :to.',
        'task_status_changed' => 'El estado cambió de :from a :to.',
    ],

    'date_labels' => [
        'semaphore_recorded_at' => 'Fecha del semáforo',
        'action_date' => 'Fecha de actuación',
        'registration_date' => 'Fecha de registro',
        'last_activity_date' => 'Fecha de última actuación',
        'speaker_changed_at' => 'Fecha de cambio de ponente',
        'unknown' => 'Fecha',
    ],

    'values' => [
        'not_available' => 'Sin información',
    ],
];

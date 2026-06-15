<?php

return [
    'app_user_role' => [
        'admin' => 'Administrador',
        'customer' => 'Cliente',
    ],
    'process_status' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'closed' => 'Cerrado',
        'pending' => 'Pendiente',
    ],
    'organization_process_status' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],
    'organization_type' => [
        'natural' => 'Persona natural',
        'juridical' => 'Persona jurídica',
    ],
    'organization_active_status' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],
    'keyword_status' => [
        'active' => 'Activa',
        'inactive' => 'Inactiva',
    ],
    'task_status' => [
        'pending' => 'Pendiente',
        'completed' => 'Cumplida',
        'draft' => 'Borrador',
    ],
    'task_urgency_level' => [
        'normal' => 'Normal',
        'alert_1' => 'Alerta (10 días)',
        'alert_2' => 'Alerta alta (15 días)',
        'critical' => 'Crítico (30+ días)',
    ],
    'process_lawyer_role' => [
        'plaintiff' => 'Demandante',
        'defendant' => 'Demandado',
    ],
    'process_import_batch_status' => [
        'processing' => 'En proceso',
        'completed' => 'Completado',
        'failed' => 'Fallido',
    ],
    'judicial_sync_run_status' => [
        'started' => 'Iniciado',
        'no_processes' => 'Sin radicados a sincronizar',
        'dispatch_failed' => 'Error al encolar el batch',
        'batch_pending' => 'Batch en cola',
        'batch_completed' => 'Completado',
        'batch_completed_with_failures' => 'Completado con fallos',
        'batch_cancelled' => 'Cancelado',
    ],
];

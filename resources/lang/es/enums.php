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
        'pending_hearing' => 'Pendiente de Audiencia',
        'suspended_insolvency' => 'Suspendido por Insolvencia',
        'suspended_payment_agreement' => 'Suspendido por Acuerdo de Pago',
        'suspended' => 'Suspendido',
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
    'task_type' => [
        'general' => 'General',
        'pending_hearing' => 'Pendiente de Audiencia',
        'suspension_insolvency' => 'Suspendido por Insolvencia',
        'suspension_payment_agreement' => 'Suspendido por Acuerdo de Pago',
        'suspension' => 'Suspensión',
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
    'process_export_status' => [
        'pending' => 'Pendiente',
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
    'judicial_sync_data_source' => [
        'judicial_branch' => 'Rama Judicial',
        'samai' => 'SAMAI',
        'tyba' => 'TYBA',
    ],
];

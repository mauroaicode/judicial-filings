<?php

return [
    'app_user_role' => [
        'admin' => 'Administrator',
        'customer' => 'Customer',
    ],
    'process_status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'closed' => 'Closed',
        'pending' => 'Pending',
    ],
    'organization_process_status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'suspended' => 'Suspended',
    ],
    'organization_type' => [
        'natural' => 'Natural person',
        'juridical' => 'Legal entity',
    ],
    'organization_active_status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],
    'keyword_status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],
    'task_status' => [
        'pending' => 'Pending',
        'completed' => 'Completed',
        'draft' => 'Draft',
    ],
    'task_type' => [
        'general' => 'General',
        'suspension' => 'Suspension',
    ],
    'task_urgency_level' => [
        'normal' => 'Normal',
        'alert_1' => 'Alert (10 days)',
        'alert_2' => 'High alert (15 days)',
        'critical' => 'Critical (30+ days)',
    ],
    'process_lawyer_role' => [
        'plaintiff' => 'Plaintiff',
        'defendant' => 'Defendant',
    ],
    'process_import_batch_status' => [
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ],
    'judicial_sync_run_status' => [
        'started' => 'Started',
        'no_processes' => 'No filings to sync',
        'dispatch_failed' => 'Failed to dispatch batch',
        'batch_pending' => 'Batch queued',
        'batch_completed' => 'Completed',
        'batch_completed_with_failures' => 'Completed with failures',
        'batch_cancelled' => 'Cancelled',
    ],
    'judicial_sync_data_source' => [
        'judicial_branch' => 'Judicial Branch',
        'samai' => 'SAMAI',
        'tyba' => 'TYBA',
    ],
];

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
];

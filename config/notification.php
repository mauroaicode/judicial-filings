<?php

return [

    'mail' => [
        'frontend_url_email_consolidated' => env('FRONTEND_URL_EMAIL_CONSOLIDATED'),
        'frontend_digest_path' => env('FRONTEND_DIGEST_PATH', '/notification-digests'),
        'digest_max_rows' => (int) env('DIGEST_EMAIL_MAX_ROWS', 0),
        'digest_table_width' => (int) env('DIGEST_EMAIL_TABLE_WIDTH', 1280),
    ],

];

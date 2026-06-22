<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | IA RAG Engine Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the settings for the AI RAG engine integration.
    |
    */

    'enabled' => env('IA_RAG_ENABLED', true),

    'keyword_detection_enabled' => env('IA_KEYWORD_DETECTION_ENABLED', false),

    'base_url' => env('IA_RAG_BASE_URL', 'http://localhost:8000'),

    'timeout' => env('IA_RAG_TIMEOUT', 180),

    'task_max_attempts' => env('IA_RAG_TASK_MAX_ATTEMPTS', 60),
    'task_retry_delay' => env('IA_RAG_TASK_RETRY_DELAY', 2),

    'queues' => [
        'sync' => env('IA_RAG_SYNC_QUEUE', 'process-sync'),
        'ai' => env('IA_RAG_AI_QUEUE', 'process-ai'),
    ],

    'prompts' => [
        'summary' => env('IA_RAG_SUMMARY_PROMPT', 'Por favor, genera un resumen ejecutivo de este proceso judicial, destacando los hitos principales y el estado actual basándote en las actuaciones proporcionadas.'),
    ],
];

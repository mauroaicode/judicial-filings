<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Alert AI Provider (OpenAI)
    |--------------------------------------------------------------------------
    |
    | Prompt, model and parameters for the AI provider used to detect alert
    | keywords in actuación annotations.
    |
    */
    'prompt' => env('ALERT_AI_PROMPT', 'Indica si el siguiente texto de una actuación judicial contiene alguna de estas palabras clave (solo responde sí o no): CONSULTA, APELACIÓN, SENTENCIA, RECHAZA, RECHAZADA, SENTENCIA. Texto: :annotation'),

    'model' => env('ALERT_AI_MODEL', 'gpt-4o-mini'),

    'temperature' => (float) env('ALERT_AI_TEMPERATURE', 0),

    'max_tokens' => (int) env('ALERT_AI_MAX_TOKENS', 10),
];

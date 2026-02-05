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
    'prompt' => env('ALERT_AI_PROMPT', 'Indica únicamente si el siguiente texto de una actuación judicial contiene alguna de las siguientes palabras clave o alguna variante con errores tipográficos mínimos (en mayúsculas o minúsculas): CONSULTA, APELACIÓN, SENTENCIA, RECHAZA, RECHAZADA. Considera también formas mal escritas que suenen o se parezcan, como por ejemplo: "CONSUTA", "APELASION", "RECHASA", etc. Si encuentras al menos una coincidencia (exacta o con error leve), responde solo: Sí. Si no encuentras ninguna, responde solo: No. Texto a analizar: :annotation'),

    'prompt_spans' => env('ALERT_AI_PROMPT_SPANS', 'El siguiente texto es de una actuación judicial. Indica si contiene alguna de estas palabras clave (o variantes con errores tipográficos leves): CONSULTA, APELACIÓN, SENTENCIA, RECHAZA, RECHAZADA. Si no encuentras ninguna, responde exactamente: No. Si encuentras una o más, responde: Sí. Fragmentos: y lista los fragmentos exactos tal como aparecen en el texto, separados por comas. Ejemplo: Sí. Fragmentos: CONSUTA, APELACIÓN. Texto a analizar: :annotation'),

    'model' => env('ALERT_AI_MODEL', 'gpt-4o-mini'),

    'temperature' => (float) env('ALERT_AI_TEMPERATURE', 0),

    'max_tokens' => (int) env('ALERT_AI_MAX_TOKENS', 50),

    'max_tokens_spans' => (int) env('ALERT_AI_MAX_TOKENS_SPANS', 150),
];

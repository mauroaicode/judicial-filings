<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | AI Chat Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the AI Chat behavior, prompts and rules.
    |
    */

    'prompt_template' => "Analiza el proceso {process_number} en Colombia con estas reglas:
1) MAPA: Demanda, Inadmisión/Subsanación, Mandamiento de pago, Notificación, Excepciones, Audiencia, Sentencia, Liquidación de costas.
2) SEMÁFORO:
   - Demandante + Inactividad >= 90d: ROJO / 45-89d: AMARILLO.
   - Demandado + Inactividad >= 90d: VERDE.
Responde solo basado en este expediente y redirige si preguntan algo fuera de este contexto jurídico.",

    'response_type' => 'paragraph',

    'modes_mapping' => [
        'agile' => 'naive',
        'strategic' => 'hybrid',
    ],
];

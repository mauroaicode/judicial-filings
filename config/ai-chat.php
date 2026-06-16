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

    'prompt_template' => 'Analiza el proceso {process_number} en Colombia con estas reglas:
1) MAPA: Demanda, Inadmisión/Subsanación, Mandamiento de pago, Notificación, Excepciones, Audiencia, Sentencia, Liquidación de costas.
2) SEMÁFORO:
   - Demandante + Inactividad >= 90d: ROJO / 45-89d: AMARILLO.
   - Demandado + Inactividad >= 90d: VERDE.
Responde solo basado en este expediente y ten en cuenta todos los datos entrenados o grafos que existan la base de datos y toma tu tiempo y redirige si preguntan algo fuera de este contexto jurídico.',

    'response_type' => 'paragraph',

    'voice_response_type' => 'paragraph',

    // Memoria de sesión en rag-api (session_id = id del AppUser autenticado).
    'enable_memory' => true,

    // source enviado a rag-api por canal.
    'chat_source' => 'chat',
    'voice_mode' => 'auto',
    'voice_source' => 'voice',

    // false = solo instrucciones de voz del rag-api (Lexa/cierre vía prompt propio desactivado).
    'voice_send_user_prompt' => true,

    /*
    | user_prompt para /voice (máx. 1000 caracteres en rag-api). No se concatena con prompt_template.
    */
    'voice_prompt_template' => 'Te llamas Lexa, asistente legal por VOZ del proceso {process_number} (Colombia). Solo el expediente; si falta info, dilo en una frase. Semáforo: demandante inactivo 90+ días ROJO, 45-89 AMARILLO; demandado inactivo 90+ días VERDE. Un párrafo continuo: sin saltos de línea, sin Markdown, listas, Referencias ni [1]. Hasta 3 oraciones, usted, conclusión primero. Tras una respuesta útil, cierra con una pregunta abierta y distinta (otra consulta, un hecho, una actuación, un plazo). Si el usuario dice no, no gracias, por ahora no, eso es todo o similar: solo una despedida breve presentándote como Lexa, con palabras distintas cada vez (ej. De acuerdo, soy Lexa y quedo atenta a lo que necesite). Mismo idioma que la pregunta. No inventes hechos.',

    'modes_mapping' => [
        'agile' => 'naive',
        'strategic' => 'hybrid',
    ],
];

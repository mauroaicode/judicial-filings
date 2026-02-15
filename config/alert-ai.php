<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Alert AI Provider: openai | ollama
    |--------------------------------------------------------------------------
    |
    | Choose which provider to use for detecting alert keywords in annotations.
    | - openai: uses OpenAI API (requires OPENAI_API_KEY in services.openai).
    | - ollama: uses local Ollama (e.g. http://127.0.0.1:11434).
    |
    */
    'provider' => env('ALERT_AI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Ollama (local) – used when provider = ollama
    |--------------------------------------------------------------------------
    */
    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
        'model' => env('ALERT_AI_OLLAMA_MODEL', 'llama3.2:3b'),
        'timeout' => (int) env('ALERT_AI_OLLAMA_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prompt template – strict words + phrases only (OpenAI and Ollama)
    |--------------------------------------------------------------------------
    |
    | Template with placeholders replaced at RUNTIME in the provider:
    |   :words   → list from config('alert-keywords.words')
    |   :phrases → list from config('alert-keywords.phrases')
    |   :annotation → texto a analizar (anotación + actuación concatenados)
    |
    */
    'prompt_spans' => env('ALERT_AI_PROMPT_SPANS', <<<'PROMPT'
Analiza el siguiente texto y busca EXACTAMENTE estas palabras o frases (ignora mayúsculas/minúsculas):

PALABRAS a buscar: :words

FRASES a buscar (debe aparecer la frase completa): :phrases

INSTRUCCIONES:
1. Busca cada palabra o frase EXACTA en el texto. La palabra debe aparecer completa (puede estar dentro de una frase más larga, pero debe ser la palabra completa).
2. Ejemplo: Si encuentras "Sentencia" en "Al Despacho para Sentencia" → devuelve "Sentencia" (la palabra completa está ahí).
3. Ejemplo: Si encuentras "Traslado" en "CyN Para Traslado" → devuelve "Traslado" (la palabra completa está ahí).
4. Para frases como "Fijación estado" o "Notificación estado": busca la frase completa que incluya "estado" (acepta variantes con typos: "Notificacion esta", "Fijacion estado").
5. IMPORTANTE - NO devuelvas palabras parciales:
   - Si el texto dice "Notificar" pero la regla es "Notificación estado" → NO devuelvas "Notificar" (es parcial).
   - Si el texto dice "Recurso" pero NO está en ninguna lista → NO lo devuelvas.
6. IMPORTANTE - Busca la palabra COMPLETA: "Sentencia" debe aparecer como "Sentencia" completa, no como parte de otra palabra.

RESPUESTA:
- Si NO encuentras ninguna palabra o frase de las listas → responde exactamente: No.
- Si encuentras una o más → responde: Sí. Fragmentos: [lista los fragmentos exactos tal como aparecen en el texto, separados por comas]

EJEMPLOS:
Texto: "Al Despacho para Sentencia"
Respuesta: Sí. Fragmentos: Sentencia

Texto: "CyN Para Traslado"
Respuesta: Sí. Fragmentos: Traslado

Texto: "Se abrió período de CONSULTA"
Respuesta: Sí. Fragmentos: CONSULTA

Texto: "Notificar a los interesados"
Respuesta: No.

Texto: "Se fijó Notificación estado del proceso"
Respuesta: Sí. Fragmentos: Notificación estado

---

TEXTO A ANALIZAR:
:annotation
PROMPT
    ),

    'model' => env('ALERT_AI_MODEL', 'gpt-4o-mini'),

    'temperature' => (float) env('ALERT_AI_TEMPERATURE', 0),

    'max_tokens' => (int) env('ALERT_AI_MAX_TOKENS', 50),

    'max_tokens_spans' => (int) env('ALERT_AI_MAX_TOKENS_SPANS', 200),
];

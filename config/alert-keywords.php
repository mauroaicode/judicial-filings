<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Alert keywords: words vs phrases (fallback detection + prompt reference)
    |--------------------------------------------------------------------------
    |
    | - words: single tokens. Match exactly (e.g. "Consulta", "Sentencia").
    | - phrases: multi-word. Match the FULL phrase only (e.g. "Fijación estado",
    | "Notificación estado"). Do NOT match partials ("Notificación" alone).
    |
    | Used by alert detection fallback and by alert-ai prompt. Canonical list
    | for DB/filtering is alert_actions_keywords (Consulta, Apelación, etc.).
    |
    */
    'words' => [
        'Consulta',
        'Apelación',
        'Sentencia',
        'Rechaza',
        'Traslado',
    ],

    'phrases' => [
        'Fijación estado',
        'Notificación estado',
    ],

    /*
    | Flat list for fallback (words + phrases). Do not add partials here.
    */
    'keywords' => [
        'Consulta',
        'Apelación',
        'Sentencia',
        'Rechaza',
        'Traslado',
        'Fijación estado',
        'Notificación estado',
    ],
];

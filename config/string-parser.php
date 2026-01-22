<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Lowercase Words
    |--------------------------------------------------------------------------
    |
    | Words that should remain lowercase when converting to title case,
    | except if they're the first word of the string.
    |
    */
    'lowercase_words' => [
        'de',
        'del',
        'y',
        'e',
        'la',
        'las',
        'el',
        'los',
        'en',
        'por',
        'para',
        'con',
        'sin',
        'sobre',
        'entre',
        'hasta',
        'desde',
        'durante',
        'mediante',
        'según',
        'a',
        'o',
        'u',
    ],

    /*
    |--------------------------------------------------------------------------
    | Abbreviations
    |--------------------------------------------------------------------------
    |
    | Abbreviations that should remain uppercase when converting to title case.
    | These can be with or without dots (e.g., 'S.A.' or 'SA').
    |
    */
    'abbreviations' => [
        's.a.',
        's.a.s.',
        'ltda.',
        's.c.',
        's.c.a.',
        'e.u.',
        'e.p.s.',
        's.a.e.',
        's.r.l.',
        's.a.p.',
        'i.p.s.',
        'e.s.p.',
        's.a.',
        's.a.s.',
        'ltda',
    ],
];

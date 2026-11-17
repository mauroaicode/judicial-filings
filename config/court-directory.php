<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Página pública del directorio
    |--------------------------------------------------------------------------
    |
    | La Rama Judicial publica el directorio dentro de un iframe de Power BI.
    | Azure Front Door bloquea la IP del datacenter. Si el proxy residencial
    | de la Rama está activo (JUDICIAL_BRANCH_PROXY_ENABLED), estas peticiones
    | salen por ese mismo proxy. No hace falta una variable COURT_DIRECTORY_*.
    |
    */

    'source_url' => env(
        'COURT_DIRECTORY_SOURCE_URL',
        'https://www.ramajudicial.gov.co/directorio-cuentas-de-correo-electronico',
    ),

    'timeout' => (int) env('COURT_DIRECTORY_TIMEOUT', 60),

    /*
    | El reporte rechaza ventanas mayores a 30.000 intersecciones.
    */
    'window_count' => (int) env('COURT_DIRECTORY_WINDOW_COUNT', 30000),

    /*
    | Cola propia en Horizon (Redis). Un solo worker escribe los lotes,
    | fuera del comando y del request de admin.
    */
    'queue' => env('COURT_DIRECTORY_QUEUE', 'court-directory'),

    'queue_connection' => env('COURT_DIRECTORY_QUEUE_CONNECTION'),

    'upsert_chunk' => 200,

    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',

];

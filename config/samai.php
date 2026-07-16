<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API REST del Consejo de Estado (SAMAI)
    |--------------------------------------------------------------------------
    |
    | URL base de la API pública de SAMAI. El modo /2 es consulta pública,
    | sin autenticación. No requiere cookies ni sesión.
    |
    */
    'api_url' => env('SAMAI_API_URL', 'https://samaicore.consejodeestado.gov.co/api'),

    'modo' => env('SAMAI_MODO', '2'),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | La API de SAMAI requiere un header "ApiKey" desde mediados de 2026.
    | Solicitarlo al equipo de sistemas del Consejo de Estado.
    | Sin esta key los endpoints de búsqueda y actuaciones no funcionan.
    | El endpoint ObtenerDatosProcesoGet sí funciona sin key.
    |
    */
    'api_key' => env('SAMAI_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Timeouts HTTP
    |--------------------------------------------------------------------------
    |
    | timeout          → peticiones estándar (actuaciones, sujetos).
    | discovery_timeout → llamadas de descubrimiento (ObtenerDatosProcesoGet),
    |                      que pueden ser lentas (~19s) en servidores departamentales.
    |                      Si se excede, el sistema envía el proceso a cola para reintentar.
    |
    */
    'timeout' => (int) env('SAMAI_TIMEOUT', 15),
    'discovery_timeout' => (int) env('SAMAI_DISCOVERY_TIMEOUT', 25),

    'log_channel' => env('SAMAI_LOG_CHANNEL', 'process_import'),

    /*
    |--------------------------------------------------------------------------
    | Registro AppUser: inline vs cola según volumen de actuaciones
    |--------------------------------------------------------------------------
    |
    | SAMAI devuelve todas las actuaciones en una sola petición (sin paginación).
    | Si el total de actuaciones de alguna instancia nueva supera este umbral,
    | el alta va en cola (SyncSamaiJob). De lo contrario termina inline.
    |
    */
    'registration_inline_max_actuaciones' => (int) env('SAMAI_REGISTRATION_INLINE_MAX_ACTUACIONES', 50),

    /*
    |--------------------------------------------------------------------------
    | Búsqueda paralela de corporación
    |--------------------------------------------------------------------------
    |
    | Cuando los primeros 7 dígitos del radicado no retornan actuaciones en SAMAI,
    | el servicio intenta Tribunales + Consejo de Estado en paralelo.
    | Este valor controla el máximo de hilos usados en esa búsqueda.
    |
    */
    'find_corporacion_max_workers' => (int) env('SAMAI_FIND_CORPORACION_MAX_WORKERS', 10),

    /*
    |--------------------------------------------------------------------------
    | Jitter entre peticiones
    |--------------------------------------------------------------------------
    */
    'call_delay_min_ms' => (int) env('SAMAI_CALL_DELAY_MIN_MS', 300),
    'call_delay_max_ms' => (int) env('SAMAI_CALL_DELAY_MAX_MS', 800),

    /*
    |--------------------------------------------------------------------------
    | Proxy (opcional — desactivado por defecto)
    |--------------------------------------------------------------------------
    |
    | La API REST de SAMAI generalmente no requiere rotación de IPs.
    | Activar solo si se detecta bloqueo por rate-limit.
    |
    */
    'proxy' => [
        'enabled' => (bool) env('SAMAI_PROXY_ENABLED', false),
        'protocol' => env('SAMAI_PROXY_PROTOCOL', 'http'),
        'host' => env('SAMAI_PROXY_HOST', ''),
        'port' => (int) env('SAMAI_PROXY_PORT', 6060),
        'username' => env('SAMAI_PROXY_USERNAME', ''),
        'password' => env('SAMAI_PROXY_PASSWORD', ''),
        'timeout' => (int) env('SAMAI_PROXY_TIMEOUT', 20),
    ],

];

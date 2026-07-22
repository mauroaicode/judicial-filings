<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API REST del Consejo de Estado (SAMAI)
    |--------------------------------------------------------------------------
    |
    | URL base de la API REST de SAMAI. El modo /2 representa la consulta
    | pública, pero algunos endpoints requieren ApiKey.
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
    | Sin esta key los endpoints de búsqueda, actuaciones y sujetos responden
    | 401. ObtenerDatosProcesoGet sigue siendo público. Para actuaciones y
    | sujetos se usa el portal HTML público configurado abajo.
    |
    */
    'api_key' => env('SAMAI_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Portal público HTML (fallback sin ApiKey)
    |--------------------------------------------------------------------------
    |
    | Consulta list_procesos.aspx manteniendo cookies y ViewState. El captcha
    | es textual y está expuesto en tres spans por la propia página; no requiere
    | OCR ni un proveedor externo. Se resuelve una sola sesión por instancia y
    | se reutiliza el resultado para actuaciones y sujetos.
    |
    */
    'public_portal' => [
        'enabled' => (bool) env('SAMAI_PUBLIC_PORTAL_ENABLED', true),
        'url' => env('SAMAI_PUBLIC_PORTAL_URL', 'https://samai.consejodeestado.gov.co'),
        'timeout' => (int) env('SAMAI_PUBLIC_PORTAL_TIMEOUT', 60),
        'connect_timeout' => (int) env('SAMAI_PUBLIC_PORTAL_CONNECT_TIMEOUT', 15),
        'max_attempts' => (int) env('SAMAI_PUBLIC_PORTAL_MAX_ATTEMPTS', 3),
        'user_agent' => env(
            'SAMAI_PUBLIC_PORTAL_USER_AGENT',
            'Mozilla/5.0 (compatible; NotiJudicial/1.0)'
        ),
        // El grid HTML trunca anotaciones con "..."; el texto completo está en el detalle "Ver".
        'expand_truncated_annotations' => (bool) env('SAMAI_PUBLIC_PORTAL_EXPAND_ANNOTATIONS', true),
        'max_expanded_annotations' => (int) env('SAMAI_PUBLIC_PORTAL_MAX_EXPANDED_ANNOTATIONS', 150),
    ],

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
    // Juzgados departamentales pueden tardar 40-60s en ObtenerDatosProcesoGet.
    'discovery_timeout' => (int) env('SAMAI_DISCOVERY_TIMEOUT', 60),

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

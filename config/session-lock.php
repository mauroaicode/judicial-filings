<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Inactivity Timeout
    |--------------------------------------------------------------------------
    |
    | Tiempo de inactividad en minutos antes de bloquear la sesión en el
    | frontend. El valor se expone al cliente vía el endpoint de configuración
    | o como variable de entorno (VITE_SESSION_LOCK_TIMEOUT_MINUTES en Angular).
    |
    */
    'inactivity_timeout' => (int) env('SESSION_LOCK_TIMEOUT_MINUTES', 5),
];

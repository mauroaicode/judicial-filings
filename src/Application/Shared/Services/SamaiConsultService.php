<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Src\Application\Shared\Exceptions\SamaiPublicPortalException;
use Throwable;

/**
 * Cliente REST para la API pública de SAMAI (Consejo de Estado).
 *
 * Base URL: https://samaicore.consejodeestado.gov.co/api
 * Modo /2 = consulta pública, sin autenticación.
 *
 * La API devuelve JSON directamente, sin cookies, Cloudflare ni paginación.
 * El proxy está disponible pero desactivado por defecto (SAMAI_PROXY_ENABLED=false).
 */
class SamaiConsultService
{
    /**
     * Corporaciones candidatas para búsqueda fallback (Consejo de Estado + 27 Tribunales).
     *
     * @var list<string>
     */
    private const CORPORACIONES_CANDIDATAS = [
        '1100103', // Consejo de Estado
        '0500123', // Tribunal Administrativo de Antioquia
        '8100123', // Tribunal Administrativo de Arauca
        '0800123', // Tribunal Administrativo del Atlántico
        '1300123', // Tribunal Administrativo de Bolívar
        '1500123', // Tribunal Administrativo de Boyacá
        '1700123', // Tribunal Administrativo de Caldas
        '1800123', // Tribunal Administrativo del Caquetá
        '8500123', // Tribunal Administrativo del Casanare
        '1900123', // Tribunal Administrativo del Cauca
        '2000123', // Tribunal Administrativo del Cesar
        '2700123', // Tribunal Administrativo del Chocó
        '2300123', // Tribunal Administrativo de Córdoba
        '2500023', // Tribunal Administrativo de Cundinamarca
        '4100123', // Tribunal Administrativo del Huila
        '4400123', // Tribunal Administrativo de la Guajira
        '4700123', // Tribunal Administrativo del Magdalena
        '5000123', // Tribunal Administrativo del Meta
        '5200123', // Tribunal Administrativo de Nariño
        '5400123', // Tribunal Administrativo de Norte de Santander
        '8600123', // Tribunal Administrativo del Putumayo
        '6300123', // Tribunal Administrativo del Quindío
        '6600123', // Tribunal Administrativo de Risaralda
        '8800123', // Tribunal Administrativo de San Andrés
        '6800123', // Tribunal Administrativo de Santander
        '7000123', // Tribunal Administrativo de Sucre
        '7300123', // Tribunal Administrativo del Tolima
        '7600123', // Tribunal Administrativo del Valle del Cauca
    ];

    private string $seed = '';

    /**
     * Evita resolver dos captchas para actuaciones y sujetos del mismo proceso.
     *
     * @var array<string, array{
     *     actuaciones: list<array<string, mixed>>,
     *     sujetos: list<array<string, mixed>>,
     *     meta: array<string, mixed>
     * }>
     */
    private array $publicPortalCache = [];

    public function __construct(
        private readonly SamaiPublicPortalService $publicPortalService,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Asocia una semilla al ciclo de vida de un radicado para logs y proxy consistentes.
     */
    public function withSeed(string $seed): static
    {
        if ($this->seed !== $seed) {
            $this->publicPortalCache = [];
        }

        $this->seed = $seed;

        return $this;
    }

    /**
     * Busca un proceso en todas las corporaciones de SAMAI.
     *
     * Intenta primero BuscarProcesoTodoSamai (requiere ApiKey desde mid-2026).
     * Si no hay ApiKey (HTTP 401) o falla, hace un fallback usando ObtenerDatosProcesoGet
     * que sí es público y no requiere autenticación.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buscarProceso(string $processNumber): array
    {
        $this->applyJitter();

        $url = $this->url("BuscarProcesoTodoSamai/{$processNumber}/{$this->modo()}");

        $this->logInfo('buscarProceso', ['url' => $url]);

        try {
            $response = $this->performRequest('get', $url);

            if ($response->successful()) {
                $data = $response->json();
                // Solo listas de resultados (índices numéricos). Evita tratar
                // wrappers tipo ['procesos' => []] como un hallazgo válido.
                if (is_array($data) && $data !== [] && array_is_list($data)) {
                    return $data;
                }
            }

            if ($response->status() === 401) {
                $this->logInfo('buscarProceso: ApiKey no configurada (401), usando fallback público');

                return $this->buscarProcesoConDatos($processNumber);
            }

            return [];
        } catch (Throwable $th) {
            $this->logError('buscarProceso failed, intentando fallback', $th);

            return $this->buscarProcesoConDatos($processNumber);
        }
    }

    /**
     * Obtiene datos completos de un proceso a partir de la corporación y el radicado.
     *
     * No requiere ApiKey — endpoint público.
     * La API devuelve {"proceso": {...}} cuando existe o {"auditoria": {}} si no existe.
     * Este método desenvuelve la clave "proceso" y retorna sus campos directamente.
     *
     * @return array<string, mixed> Campos del proceso, o [] si no existe.
     */
    public function obtenerDatosProceso(string $corporacion, string $processNumber): array
    {
        $this->applyJitter();

        $url = $this->url("ObtenerDatosProcesoGet/{$corporacion}/{$processNumber}/{$this->modo()}");
        // Mismo endpoint usado en discovery: en juzgados departamentales puede
        // tardar >15s. Usamos discovery_timeout para no perder procesos válidos.
        $httpTimeout = (int) config('samai.discovery_timeout', 25);

        $this->logInfo('obtenerDatosProceso', [
            'corporacion' => $corporacion,
            'url' => $url,
            'timeout' => $httpTimeout,
        ]);

        try {
            $response = Http::timeout($httpTimeout)
                ->withHeaders($this->defaultHeaders())
                ->get($url);
            $data = $response->json();

            if (! is_array($data)) {
                return [];
            }

            // Desenvolver la clave "proceso" que envuelve los datos reales.
            $proceso = $data['proceso'] ?? null;

            if (is_array($proceso) && $proceso !== []) {
                return $this->enrichProcesoMetaFromPortal($proceso, $corporacion, $processNumber);
            }

            // Sin datos REST (o respuesta vacía): usar metadatos del portal público
            // para no crear procesos sin Despacho/Clase.
            if ((bool) config('samai.public_portal.enabled', true)) {
                $this->logInfo('obtenerDatosProceso vacío, usando metadatos del portal público', [
                    'corporacion' => $corporacion,
                    'process_number' => $processNumber,
                ]);

                return $this->publicPortalData($corporacion, $processNumber)['meta'];
            }

            return [];
        } catch (SamaiPublicPortalException $exception) {
            $this->logError('obtenerDatosProceso public portal failed', $exception);

            throw $exception;
        } catch (Throwable $th) {
            $this->logError('obtenerDatosProceso failed', $th);

            // Timeout/conexión: no devolver [] (crearía procesos sin Despacho/Clase).
            // Propagar para que import/sync reintenten.
            if ($this->isTimeoutException($th)) {
                throw new \Src\Application\Shared\Exceptions\SamaiDiscoveryTimeoutException($processNumber);
            }

            return [];
        }
    }

    /**
     * REST a veces no trae el label "Origen"; el portal sí. Sin eso el despacho
     * cae a NombreSalaDecision genérico ("Juzgado Administrativo - Ciudad").
     *
     * @param  array<string, mixed>  $proceso
     * @return array<string, mixed>
     */
    private function enrichProcesoMetaFromPortal(array $proceso, string $corporacion, string $processNumber): array
    {
        $hasOrigenName = trim((string) ($proceso['Origen'] ?? '')) !== ''
            || (
                trim((string) ($proceso['EntidadRadicadora'] ?? '')) !== ''
                && preg_match('/^\d+$/', trim((string) $proceso['EntidadRadicadora'])) !== 1
            );

        if ($hasOrigenName || ! (bool) config('samai.public_portal.enabled', true)) {
            return $proceso;
        }

        try {
            $meta = $this->publicPortalData($corporacion, $processNumber)['meta'];
            foreach (['Origen', 'EntidadRadicadora', 'cityName'] as $key) {
                if (
                    trim((string) ($proceso[$key] ?? '')) === ''
                    && trim((string) ($meta[$key] ?? '')) !== ''
                ) {
                    $proceso[$key] = $meta[$key];
                }
            }
        } catch (Throwable $exception) {
            $this->logInfo('obtenerDatosProceso: no se pudo enriquecer Origen desde portal', [
                'corporacion' => $corporacion,
                'process_number' => $processNumber,
                'error' => $exception->getMessage(),
            ]);
        }

        return $proceso;
    }

    /**
     * Obtiene el historial completo de actuaciones de un proceso.
     * SAMAI devuelve todas las actuaciones en una sola petición (sin paginación).
     *
     * @return object{isSuccessful: bool, data: array<int, array<string, mixed>>}
     */
    public function obtenerActuaciones(string $corporacion, string $processNumber): object
    {
        if ($this->shouldUsePublicPortal()) {
            return (object) [
                'isSuccessful' => true,
                'data' => $this->publicPortalData($corporacion, $processNumber)['actuaciones'],
            ];
        }

        $this->applyJitter();

        $url = $this->url("Procesos/HistorialActuaciones/{$corporacion}/{$processNumber}/{$this->modo()}");

        $this->logInfo('obtenerActuaciones', ['corporacion' => $corporacion, 'url' => $url]);

        try {
            $response = $this->performRequest('get', $url);

            if ($response->successful()) {
                $data = $response->json();
                $list = is_array($data) && array_is_list($data) ? $data : [];

                // API Key inválida/parcial a veces responde 200 con lista vacía.
                // En ese caso caemos al portal público para no marcar el sync como OK vacío.
                if ($list === [] && (bool) config('samai.public_portal.enabled', true)) {
                    $this->logInfo('obtenerActuaciones API vacía, usando portal público', [
                        'corporacion' => $corporacion,
                        'process_number' => $processNumber,
                    ]);

                    return (object) [
                        'isSuccessful' => true,
                        'data' => $this->publicPortalData($corporacion, $processNumber)['actuaciones'],
                    ];
                }

                return (object) [
                    'isSuccessful' => true,
                    'data' => $list,
                ];
            }

            if (in_array($response->status(), [401, 403], true)) {
                return (object) [
                    'isSuccessful' => true,
                    'data' => $this->publicPortalData($corporacion, $processNumber)['actuaciones'],
                ];
            }

            return (object) ['isSuccessful' => false, 'data' => []];
        } catch (SamaiPublicPortalException $exception) {
            $this->logError('obtenerActuaciones public portal failed', $exception);

            throw $exception;
        } catch (Throwable $th) {
            $this->logError('obtenerActuaciones failed', $th);

            return (object) ['isSuccessful' => false, 'data' => []];
        }
    }

    /**
     * Obtiene las partes procesales (demandante, demandado, etc.) de un proceso.
     *
     * @return object{isSuccessful: bool, data: array<int, array<string, mixed>>}
     */
    public function obtenerSujetosProcesales(string $corporacion, string $processNumber): object
    {
        if ($this->shouldUsePublicPortal()) {
            return (object) [
                'isSuccessful' => true,
                'data' => $this->publicPortalData($corporacion, $processNumber)['sujetos'],
            ];
        }

        $this->applyJitter();

        $url = $this->url("Procesos/SujetosProcesales/{$corporacion}/{$processNumber}/{$this->modo()}");

        $this->logInfo('obtenerSujetosProcesales', ['corporacion' => $corporacion, 'url' => $url]);

        try {
            $response = $this->performRequest('get', $url);

            if ($response->successful()) {
                $data = $response->json();
                $list = is_array($data) && array_is_list($data) ? $data : [];

                if ($list === [] && (bool) config('samai.public_portal.enabled', true)) {
                    $this->logInfo('obtenerSujetosProcesales API vacía, usando portal público', [
                        'corporacion' => $corporacion,
                        'process_number' => $processNumber,
                    ]);

                    return (object) [
                        'isSuccessful' => true,
                        'data' => $this->publicPortalData($corporacion, $processNumber)['sujetos'],
                    ];
                }

                return (object) [
                    'isSuccessful' => true,
                    'data' => $list,
                ];
            }

            if (in_array($response->status(), [401, 403], true)) {
                return (object) [
                    'isSuccessful' => true,
                    'data' => $this->publicPortalData($corporacion, $processNumber)['sujetos'],
                ];
            }

            return (object) ['isSuccessful' => false, 'data' => []];
        } catch (SamaiPublicPortalException $exception) {
            $this->logError('obtenerSujetosProcesales public portal failed', $exception);

            throw $exception;
        } catch (Throwable $th) {
            $this->logError('obtenerSujetosProcesales failed', $th);

            return (object) ['isSuccessful' => false, 'data' => []];
        }
    }

    /**
     * Cuenta las actuaciones de un proceso para decidir registro inline vs cola.
     * Llama a obtenerActuaciones internamente.
     */
    public function contarActuaciones(string $corporacion, string $processNumber): int
    {
        $result = $this->obtenerActuaciones($corporacion, $processNumber);

        return $result->isSuccessful ? count($result->data) : 0;
    }

    private function shouldUsePublicPortal(): bool
    {
        return (string) config('samai.api_key', '') === ''
            && (bool) config('samai.public_portal.enabled', true);
    }

    /**
     * @return array{
     *     actuaciones: list<array<string, mixed>>,
     *     sujetos: list<array<string, mixed>>,
     *     meta: array<string, mixed>
     * }
     */
    private function publicPortalData(string $corporacion, string $processNumber): array
    {
        if (! (bool) config('samai.public_portal.enabled', true)) {
            throw new SamaiPublicPortalException('El fallback al portal público de SAMAI está deshabilitado.');
        }

        $key = $processNumber.'|'.$corporacion;

        return $this->publicPortalCache[$key]
            ??= $this->publicPortalService->fetch($processNumber, $corporacion);
    }

    /**
     * Extrae la corporación desde el resultado de buscarProceso.
     * Intenta múltiples nombres de campo (la API usa mayúsculas/minúsculas variadas).
     *
     * Si el resultado de la búsqueda no contiene corporación, usa los primeros 7 dígitos
     * del radicado como fallback estándar para juzgados administrativos.
     *
     * @param  array<string, mixed>  $searchResult  Un elemento del array devuelto por buscarProceso
     */
    public function extractCorporacion(array $searchResult, string $processNumber): string
    {
        foreach (['Corporacion', 'corporacion', 'CodCorporacion', 'codCorporacion', 'CodigoCorporacion'] as $key) {
            $val = $searchResult[$key] ?? null;
            if (is_string($val) && $val !== '') {
                return $val;
            }
        }

        // Fallback: primeros 7 dígitos del radicado (corporación del juzgado de origen)
        return substr($processNumber, 0, 7);
    }

    /**
     * Busca la corporación correcta probando candidatos secuencialmente.
     *
     * Usa ObtenerDatosProcesoGet (endpoint público, sin ApiKey) para detectar
     * qué corporación tiene datos del radicado.
     *
     * Orden de búsqueda optimizado para minimizar llamadas a la API:
     *  1. Primeros 7 dígitos del radicado (corporación del juzgado de origen).
     *  2. Tribunal del departamento (derivado de los primeros 2 dígitos).
     *  3. Consejo de Estado.
     *  4. Resto de Tribunales si los pasos anteriores fallan.
     *
     * @param  list<string>  $excluir  Corporaciones ya descartadas.
     */
    public function encontrarCorporacion(string $processNumber, array $excluir = []): ?string
    {
        $excluirSet = array_flip($excluir);

        $prioritized = $this->discoveryCorporacionCandidates($processNumber);

        $remaining = array_filter(
            self::CORPORACIONES_CANDIDATAS,
            fn (string $c): bool => ! in_array($c, $prioritized, true)
        );

        $candidates = array_values(array_filter(
            array_merge($prioritized, $remaining),
            fn (string $c): bool => ! isset($excluirSet[$c])
        ));

        $httpTimeout = (int) config('samai.discovery_timeout', 22);

        foreach ($candidates as $corp) {
            try {
                $url = $this->url("ObtenerDatosProcesoGet/{$corp}/{$processNumber}/{$this->modo()}");
                $client = Http::timeout($httpTimeout)->withHeaders($this->defaultHeaders());
                $resp = $client->get($url);

                if (! $resp->successful()) {
                    continue;
                }

                $data = $resp->json();
                $proceso = is_array($data) ? ($data['proceso'] ?? null) : null;
                if (! is_array($proceso)) {
                    continue;
                }

                if ($proceso === []) {
                    continue;
                }

                // Extraer corporación real desde EntidadRadicadora
                $entidad = (string) ($proceso['EntidadRadicadora'] ?? '');
                $actualCorp = strlen($entidad) >= 7 ? substr($entidad, 0, 7) : $corp;

                $this->logInfo('encontrarCorporacion: corporación encontrada', [
                    'corporacion' => $actualCorp,
                    'via_corp' => $corp,
                    'process_number' => $processNumber,
                ]);

                return $actualCorp;
            } catch (Throwable) {
                continue;
            }
        }

        $portalHit = $this->discoverViaPublicPortal(
            $processNumber,
            array_values(array_filter(
                $prioritized,
                fn (string $c): bool => ! isset($excluirSet[$c])
            ))
        );
        if ($portalHit !== []) {
            $corp = $portalHit[0]['Corporacion'] ?? null;

            return is_string($corp) && $corp !== '' ? $corp : null;
        }

        $this->logInfo('encontrarCorporacion: no se encontró corporación', [
            'process_number' => $processNumber,
        ]);

        return null;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Fallback de buscarProceso usando ObtenerDatosProcesoGet (no requiere ApiKey).
     *
     * ObtenerDatosProcesoGet puede devolver datos sin importar la corporación enviada
     * (comportamiento observado en la API), por lo que no podemos usar el parámetro `corp`
     * como fuente de verdad. En cambio, extraemos la corporación real desde el campo
     * `EntidadRadicadora` de la respuesta, cuyos primeros 7 dígitos corresponden al
     * código SAMAI de la corporación correcta (ej. "760012333000" → "7600123").
     *
     * Solo se intentan 3 candidatos prioritarios para evitar latencias:
     *  primeros 7 dígitos, tribunal del departamento, Consejo de Estado.
     * Si ninguno responde, se devuelve [].
     *
     * @return array<int, array<string, mixed>>
     */
    private function buscarProcesoConDatos(string $processNumber): array
    {
        $candidates = $this->discoveryCorporacionCandidates($processNumber);

        $timedOut = false;
        $httpTimeout = (int) config('samai.discovery_timeout', 25);

        foreach ($candidates as $corp) {
            try {
                $url = $this->url("ObtenerDatosProcesoGet/{$corp}/{$processNumber}/{$this->modo()}");
                $client = Http::timeout($httpTimeout)->withHeaders($this->defaultHeaders());
                $resp = $client->get($url);

                if (! $resp->successful()) {
                    continue;
                }

                $data = $resp->json();
                $proceso = is_array($data) ? ($data['proceso'] ?? null) : null;
                if (! is_array($proceso)) {
                    continue;
                }

                if ($proceso === []) {
                    continue;
                }

                // EntidadRadicadora contiene el código del despacho radicador (12 dígitos).
                // Sus primeros 7 dígitos son el código SAMAI de la corporación real.
                $entidad = (string) ($proceso['EntidadRadicadora'] ?? '');
                $actualCorp = strlen($entidad) >= 7 ? substr($entidad, 0, 7) : $corp;

                $this->logInfo('buscarProcesoConDatos: proceso encontrado', [
                    'process_number' => $processNumber,
                    'corporacion' => $actualCorp,
                    'via_corp' => $corp,
                ]);

                return [['Corporacion' => $actualCorp, 'llaveProceso' => $processNumber]];
            } catch (Throwable $exception) {
                if ($this->isTimeoutException($exception)) {
                    $timedOut = true;
                    $this->logInfo('buscarProcesoConDatos: timeout REST, probando portal', [
                        'process_number' => $processNumber,
                        'corporacion' => $corp,
                        'timeout' => $httpTimeout,
                    ]);

                    $portalHit = $this->discoverViaPublicPortal($processNumber, [$corp]);
                    if ($portalHit !== []) {
                        return $portalHit;
                    }
                }

                continue;
            }
        }

        // REST no resolvió (timeout o vacío). El portal suele ser más rápido
        // y estable para juzgados departamentales lentos.
        $portalHit = $this->discoverViaPublicPortal($processNumber, $candidates);
        if ($portalHit !== []) {
            return $portalHit;
        }

        if ($timedOut) {
            // Al menos un candidato hizo timeout → el proceso puede existir pero SAMAI estaba lento.
            // Lanzar excepción específica para que el caller lo reintente en cola en vez de 404.
            throw new \Src\Application\Shared\Exceptions\SamaiDiscoveryTimeoutException($processNumber);
        }

        $this->logInfo('buscarProcesoConDatos: proceso no encontrado en candidatos prioritarios', [
            'process_number' => $processNumber,
            'candidates' => $candidates,
        ]);

        return [];
    }

    /**
     * Corporaciones a probar al descubrir un radicado sin ApiKey.
     *
     * Incluye una variante embebida: a veces los dígitos 6-7 son especialidad
     * y 8-9 el juzgado real (ej. 630012333000... → 6300133).
     *
     * @return list<string>
     */
    private function discoveryCorporacionCandidates(string $processNumber): array
    {
        $defaultCorp = substr($processNumber, 0, 7);
        $deptTribunal = $this->departmentTribunalCode($processNumber);
        $embeddedJuzgado = null;

        if (strlen($processNumber) >= 9) {
            $embeddedJuzgado = substr($processNumber, 0, 5).substr($processNumber, 7, 2);
            if ($embeddedJuzgado === $defaultCorp) {
                $embeddedJuzgado = null;
            }
        }

        return array_values(array_unique(array_filter([
            $defaultCorp,
            $embeddedJuzgado,
            $deptTribunal,
            '1100103',
        ])));
    }

    /**
     * Descubre la corporación vía portal HTML y cachea actuaciones/sujetos.
     *
     * @param  list<string>  $candidates
     * @return array<int, array<string, mixed>>
     */
    private function discoverViaPublicPortal(string $processNumber, array $candidates): array
    {
        if (! (bool) config('samai.public_portal.enabled', true)) {
            return [];
        }

        foreach ($candidates as $corp) {
            if ($corp === '') {
                continue;
            }

            try {
                $portalData = $this->publicPortalService->fetch($processNumber, $corp);
                $cacheKey = $processNumber.'|'.$corp;
                $this->publicPortalCache[$cacheKey] = $portalData;

                $this->logInfo('buscarProcesoConDatos: proceso encontrado vía portal público', [
                    'process_number' => $processNumber,
                    'corporacion' => $corp,
                    'actuaciones' => count($portalData['actuaciones']),
                    'sujetos' => count($portalData['sujetos']),
                ]);

                return [['Corporacion' => $corp, 'llaveProceso' => $processNumber]];
            } catch (SamaiPublicPortalException $exception) {
                $this->logInfo('buscarProcesoConDatos: portal no resolvió candidato', [
                    'process_number' => $processNumber,
                    'corporacion' => $corp,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return [];
    }

    private function isTimeoutException(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'curl error 28');
    }

    /**
     * Devuelve el código de Tribunal Administrativo del departamento
     * basado en los primeros 2 dígitos del radicado.
     */
    private function departmentTribunalCode(string $processNumber): ?string
    {
        $dept = substr($processNumber, 0, 2);

        $map = [
            '05' => '0500123', // Antioquia
            '08' => '0800123', // Atlántico
            '11' => '1100103', // Bogotá D.C. → Consejo de Estado / Cundinamarca
            '13' => '1300123', // Bolívar
            '15' => '1500123', // Boyacá
            '17' => '1700123', // Caldas
            '18' => '1800123', // Caquetá
            '19' => '1900123', // Cauca
            '20' => '2000123', // Cesar
            '23' => '2300123', // Córdoba
            '25' => '2500023', // Cundinamarca (código especial)
            '27' => '2700123', // Chocó
            '41' => '4100123', // Huila
            '44' => '4400123', // Guajira
            '47' => '4700123', // Magdalena
            '50' => '5000123', // Meta
            '52' => '5200123', // Nariño
            '54' => '5400123', // Norte de Santander
            '63' => '6300123', // Quindío
            '66' => '6600123', // Risaralda
            '68' => '6800123', // Santander
            '70' => '7000123', // Sucre
            '73' => '7300123', // Tolima
            '76' => '7600123', // Valle del Cauca
            '81' => '8100123', // Arauca
            '85' => '8500123', // Casanare
            '86' => '8600123', // Putumayo
            '88' => '8800123', // San Andrés
        ];

        return $map[$dept] ?? null;
    }

    private function url(string $path): string
    {
        $base = rtrim((string) config('samai.api_url', 'https://samaicore.consejodeestado.gov.co/api'), '/');

        return "{$base}/{$path}";
    }

    private function modo(): string
    {
        return (string) config('samai.modo', '2');
    }

    /**
     * @return array<string, string>
     */
    private function defaultHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Accept-Language' => 'es-CO,es;q=0.9',
            'Connection' => 'keep-alive',
        ];

        $apiKey = (string) config('samai.api_key', '');
        if ($apiKey !== '') {
            $headers['ApiKey'] = $apiKey;
        }

        return $headers;
    }

    private function buildHttpClient(): PendingRequest
    {
        $timeout = (int) config('samai.timeout', 15);

        $client = Http::timeout($timeout)->withHeaders($this->defaultHeaders());

        if ((bool) config('samai.proxy.enabled', false)) {
            $protocol = config('samai.proxy.protocol', 'http');
            $host = config('samai.proxy.host', '');
            $port = (int) config('samai.proxy.port', 6060);
            $username = config('samai.proxy.username', '');
            $password = config('samai.proxy.password', '');

            if ($host !== '') {
                $proxyUrl = $username !== ''
                    ? "{$protocol}://{$username}:{$password}@{$host}:{$port}"
                    : "{$protocol}://{$host}:{$port}";

                $client = $client->withOptions(['proxy' => $proxyUrl]);

                $this->logInfo('Usando proxy', ['proxy' => "{$protocol}://{$host}:{$port}"]);
            }
        }

        return $client;
    }

    private function performRequest(string $method, string $url): Response
    {
        $client = $this->buildHttpClient();

        /** @var Response $response */
        $response = $client->$method($url);

        if (! $response->successful()) {
            $this->logInfo("HTTP {$response->status()} en {$url}");
        }

        return $response;
    }

    private function applyJitter(): void
    {
        $minMs = (int) config('samai.call_delay_min_ms', 300);
        $maxMs = (int) config('samai.call_delay_max_ms', 800);

        if ($minMs >= $maxMs) {
            $maxMs = $minMs + 200;
        }

        $jitterMs = random_int($minMs, $maxMs);
        Sleep::usleep($jitterMs * 1000);
    }

    private function logInfo(string $message, array $context = []): void
    {
        Log::channel(config('samai.log_channel', 'process_import'))
            ->info("[SAMAI] {$message}", array_merge(['seed' => $this->seed], $context));
    }

    private function logError(string $message, Throwable $th): void
    {
        Log::channel(config('samai.log_channel', 'process_import'))
            ->error("[SAMAI] {$message}", [
                'seed' => $this->seed,
                'exception' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
    }
}

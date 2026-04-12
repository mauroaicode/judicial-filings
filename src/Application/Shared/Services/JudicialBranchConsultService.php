<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Src\Application\Shared\Exceptions\ApiEmptyProcessesException;
use Src\Application\Shared\Exceptions\ApiForbiddenOrRateLimitException;
use Src\Application\Shared\Exceptions\ApiProxyFailureException;
use Throwable;

class JudicialBranchConsultService
{
    /**
     * Pool de User-Agents modernos y realistas.
     * El índice se deriva del $radicadoSeed para que las 4 peticiones de un mismo
     * radicado siempre presenten el mismo navegador a Cloudflare.
     *
     * @var list<string>
     */
    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 Edg/122.0.0.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:124.0) Gecko/20100101 Firefox/124.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Safari/605.1.15',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 Edg/122.0.0.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:123.0) Gecko/20100101 Firefox/123.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_3_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3 Safari/605.1.15',
        'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:124.0) Gecko/20100101 Firefox/124.0',
    ];

    /**
     * Seed derivado del número de radicado.
     * Garantiza que las 4 peticiones del mismo radicado usen el mismo User-Agent,
     * manteniendo coherencia de huella de navegador ante Cloudflare.
     */
    private string $radicadoSeed = '';

    /**
     * Cookie jar persistente por ciclo de vida de un radicado.
     */
    private ?CookieJar $cookieJar = null;

    public function __construct(
        private readonly ProxyPoolService $proxyPool,
    ) {}

    /**
     * Sets the radicado seed for this service instance.
     * Call this once per job with the process number so all 4 requests
     * of the same radicado share the same User-Agent fingerprint.
     */
    public function withSeed(string $seed): static
    {
        $this->radicadoSeed = $seed;
        $this->cookieJar = new CookieJar;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Public API methods
    // -------------------------------------------------------------------------

    /**
     * Fetches a list of processes by filing code.
     *
     * @param  string  $code  Filing code (radicado number).
     * @return object{isSuccessful: bool, data: array<mixed>}
     */
    public function fetchProcesses(string $code): object
    {
        $data = [];
        $isSuccessful = true;

        try {
            $baseUrl = config('judicial-branch.api_url').'/Procesos/Consulta/NumeroRadicacion';
            $allProcesses = [];
            $currentPage = 1;
            $totalPages = 1;

            $client = $this->buildHttpClient();

            do {
                // Apply jitter before each page request (including the first one to avoid rapid fire)
                // Usamos delay normal para la p1, y rápido para las siguientes (clic de paginación)
                $this->applyJitter($currentPage > 1);

                $params = [
                    'numero' => $code,
                    'SoloActivos' => 'false',
                    'pagina' => $currentPage,
                ];

                $endpoint = "{$baseUrl}?".http_build_query($params);

                Log::channel(config('judicial-branch.log_channel', 'process_import'))
                    ->info('JudicialBranch: Fetching processes', ['url' => $endpoint]);

                $httpResponse = $this->performRequestWithRetries(fn () => $client->get($endpoint), 'fetchProcesses');

                $response = $httpResponse->json();
                if (! is_array($response)) {
                    $response = [];
                }

                if (isset($response['procesos'])) {
                    $allProcesses = array_merge($allProcesses, $response['procesos']);
                }

                if (isset($response['paginacion'])) {
                    $totalPages = (int) ($response['paginacion']['cantidadPaginas'] ?? 1);
                }

                $currentPage++;
            } while ($currentPage <= $totalPages);

            $data = $allProcesses;

            if ($allProcesses === []) {
                throw new ApiEmptyProcessesException(__('process.api_empty_processes'));
            }
        } catch (ApiEmptyProcessesException|ApiForbiddenOrRateLimitException|ApiProxyFailureException $e) {
            throw $e;
        } catch (Throwable $th) {
            $isSuccessful = false;
            $this->logError('Error fetching processes', $th);
        }

        return (object) ['isSuccessful' => $isSuccessful, 'data' => $data];
    }

    /**
     * Fetches detailed information of a specific process.
     *
     * @param  int  $processId  Unique ID of the process.
     * @return object{isSuccessful: bool, data: array<mixed>}
     */
    public function fetchDetailProcess(int $processId): object
    {
        $data = [];
        $isSuccessful = true;

        try {
            $this->applyJitter();

            $endpoint = config('judicial-branch.api_url')."/Proceso/Detalle/{$processId}";

            $client = $this->buildHttpClient();

            $httpResponse = $this->performRequestWithRetries(fn () => $client->get($endpoint), 'fetchDetailProcess');

            $data = $httpResponse->json() ?? [];

        } catch (ApiForbiddenOrRateLimitException|ApiProxyFailureException $e) {
            throw $e;
        } catch (Throwable $th) {
            $isSuccessful = false;
            $this->logError('Error fetching process detail', $th);
        }

        return (object) ['isSuccessful' => $isSuccessful, 'data' => $data];
    }

    /**
     * Fetches all actions for a specific process, handling pagination.
     *
     * @param  int  $processId  Unique ID of the process.
     * @param  bool  $onlyFirstPage  If true, only the first page will be fetched.
     * @return object{isSuccessful: bool, data: array<mixed>}
     */
    public function fetchActionByProcess(int $processId, bool $onlyFirstPage = false): object
    {
        $data = [];
        $isSuccessful = true;

        try {
            $baseUrl = config('judicial-branch.api_url')."/Proceso/Actuaciones/{$processId}";
            $allActions = [];
            $currentPage = 1;
            $totalPages = 1;

            $client = $this->buildHttpClient();

            do {
                $this->applyJitter($currentPage > 1);

                $params = ['pagina' => $currentPage];
                $endpoint = "{$baseUrl}?".http_build_query($params);

                $httpResponse = $this->performRequestWithRetries(fn () => $client->get($endpoint), 'fetchActionByProcess');

                $response = $httpResponse->json();
                if (! is_array($response)) {
                    $response = [];
                }

                if (isset($response['actuaciones'])) {
                    $allActions = array_merge($allActions, $response['actuaciones']);
                }

                if (isset($response['paginacion'])) {
                    $totalPages = (int) ($response['paginacion']['cantidadPaginas'] ?? 1);
                }

                if ($onlyFirstPage) {
                    break;
                }

                $currentPage++;
            } while ($currentPage <= $totalPages);

            $data = $allActions;

        } catch (ApiForbiddenOrRateLimitException|ApiProxyFailureException $e) {
            throw $e;
        } catch (Throwable $th) {
            $isSuccessful = false;
            $this->logError('Error fetching process actions', $th);
        }

        return (object) ['isSuccessful' => $isSuccessful, 'data' => $data];
    }

    /**
     * Fetches all subjects for a specific process, handling pagination.
     *
     * @param  int  $processId  Unique ID of the process.
     * @return object{isSuccessful: bool, data: array<mixed>}
     */
    public function fetchSubjectsByProcess(int $processId): object
    {
        $data = [];
        $isSuccessful = true;

        try {
            $baseUrl = config('judicial-branch.api_url')."/Proceso/Sujetos/{$processId}";
            $allSubjects = [];
            $currentPage = 1;
            $totalPages = 1;

            $client = $this->buildHttpClient();

            do {
                $this->applyJitter($currentPage > 1);

                $params = ['pagina' => $currentPage];
                $endpoint = "{$baseUrl}?".http_build_query($params);

                $httpResponse = $this->performRequestWithRetries(fn () => $client->get($endpoint), 'fetchSubjectsByProcess');

                $response = $httpResponse->json();
                if (! is_array($response)) {
                    $response = [];
                }

                if (isset($response['sujetos'])) {
                    $allSubjects = array_merge($allSubjects, $response['sujetos']);
                }

                if (isset($response['paginacion'])) {
                    $totalPages = (int) ($response['paginacion']['cantidadPaginas'] ?? 1);
                }

                $currentPage++;
            } while ($currentPage <= $totalPages);

            $data = $allSubjects;

        } catch (ApiForbiddenOrRateLimitException|ApiProxyFailureException $e) {
            throw $e;
        } catch (Throwable $th) {
            $isSuccessful = false;
            $this->logError('Error fetching process subjects', $th);
        }

        return (object) ['isSuccessful' => $isSuccessful, 'data' => $data];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Builds an HTTP client with:
     *  - User-Agent determinístico por radicado (mismo UA en todas las peticiones)
     *  - Cabeceras humanas realistas para reducir huella ante Cloudflare
     *  - CookieJar compartido para persistir cf_clearance entre peticiones
     *  - Proxy SOCKS5 (o configurado) para puerto 448
     */
    private function buildHttpClient(): PendingRequest
    {
        $proxyEnabled = config('judicial-branch.proxy.enabled', false);
        $timeout = (int) config('judicial-branch.proxy.timeout', 45);
        $userAgent = $this->resolveUserAgent();

        $client = Http::timeout($timeout)
            ->withHeaders([
                'User-Agent' => $userAgent,
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Language' => 'es-CO,es;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'Sec-Fetch-Dest' => 'empty',
                'Sec-Fetch-Mode' => 'cors',
                'Sec-Fetch-Site' => 'same-origin',
                'Sec-Ch-Ua-Mobile' => '?0',
                'DNT' => '1',
            ])
            ->withOptions(['cookies' => $this->cookieJar ?? new CookieJar]);

        if ($proxyEnabled) {
            // Pass the radicadoSeed to next() to enable Sticky Sessions (same IP for all requests of this radicado)
            $proxyUrl = $this->proxyPool->next($this->radicadoSeed);

            if ($proxyUrl !== null) {
                $client = $client->withOptions([
                    'proxy' => $proxyUrl,
                ]);

                $this->logInfo('Executing request', [
                    'proxy_url'  => $proxyUrl,
                    'user_agent' => $userAgent,
                ]);
            } else {
                $this->logInfo('Executing request (direct, no proxy available)', [
                    'user_agent' => $userAgent,
                ]);
            }
        } else {
            $this->logInfo('Executing request (direct connection)', [
                'user_agent' => $userAgent,
            ]);
        }

        return $client;
    }

    private function performRequestWithRetries(callable $request, string $context): Response
    {
        try {
            /** @var Response $response */
            $response = $request();

            $status = $response->status();

            // If successful (or not a retryable error like 404), return immediately
            if ($status < 400 || $status === 404) {
                // Soft-block detection: If we expect JSON but receive an HTML 200 OK, 
                // it means the proxy or Azure WAF returned a Captcha or SPA fallback page.
                $contentType = $response->header('Content-Type') ?? '';
                if ($status === 200 && str_contains($contentType, 'text/html')) {
                    $this->throwIfForbiddenOrRateLimit(403, $context, $response);
                }

                return $response;
            }

            // Handle 403 (Forbidden) or 429 (Too Many Requests)
            if ($status === 403 || $status === 429) {
                // ¡FALLO RÁPIDO!
                $this->throwIfForbiddenOrRateLimit($status, $context, $response);
            }

            // For other errors, just return the response and let the caller handle it
            return $response;

        } catch (Throwable $th) {
            // Ignore API exceptions we just threw
            if ($th instanceof ApiForbiddenOrRateLimitException) {
                throw $th;
            }

            // Tratamos todo error de conexión (timeout, proxy drop) como fallo rápido
            // para que Laravel genere una nueva sesión y pase de proxy.
            $this->throwIfProxyFailure($th, $context);
            
            throw $th;
        }
    }

    /**
     * Handles waiting before a retry, using Retry-After header or exponential backoff.
     */
    private function handleCooldown(?Response $response, int $attempt): void
    {
        $seconds = 0;

        // 1. Check for Retry-After header
        if ($response instanceof \Illuminate\Http\Client\Response) {
            $retryAfter = $response->header('Retry-After');
            if (! empty($retryAfter)) {
                $seconds = is_numeric($retryAfter)
                    ? (int) $retryAfter
                    : max(0, strtotime($retryAfter) - time());
            }
        }

        // 2. If no Retry-After, apply exponential backoff (2s, 4s, 8s)
        if ($seconds <= 0) {
            $seconds = (int) 2 ** ($attempt + 1);
        }

        $this->logInfo("Pausing for {$seconds}s before retry...", ['attempt' => $attempt]);
        Sleep::sleep($seconds);
    }

    /**
     * Selects a User-Agent deterministically from the radicado seed.
     */
    private function resolveUserAgent(): string
    {
        $count = count(self::USER_AGENTS);

        $index = $this->radicadoSeed !== ''
            ? (abs(crc32($this->radicadoSeed)) % $count)
            : random_int(0, $count - 1);

        return self::USER_AGENTS[$index];
    }

    /**
     * Paces HTTP calls using a random jitter delay.
     *
     * @param  bool  $fast  If true, uses a shorter delay suitable for pagination clicks.
     */
    private function applyJitter(bool $fast = false): void
    {
        $minMs = (int) config('judicial-branch.proxy.call_delay_min_ms', 1000);
        $maxMs = (int) config('judicial-branch.proxy.call_delay_max_ms', 2500);

        if ($fast) {
            $minMs = (int) floor($minMs * 0.4); // 60% más rápido para clics de página
            $maxMs = (int) floor($maxMs * 0.6);
        }

        if ($minMs >= $maxMs) {
            $maxMs = $minMs + 500;
        }

        $jitterMs = random_int($minMs, $maxMs);

        $this->logInfo('Applying jitter delay', ['delay_ms' => $jitterMs, 'mode' => $fast ? 'fast' : 'normal']);

        Sleep::usleep($jitterMs * 1000);
    }

    /**
     * Throws ApiForbiddenOrRateLimitException on HTTP 403 or 429.
     */
    private function throwIfForbiddenOrRateLimit(int $status, string $context, ?Response $httpResponse = null): void
    {
        $retryAfter = null;

        if ($httpResponse instanceof \Illuminate\Http\Client\Response) {
            $retryAfterHeader = $httpResponse->header('Retry-After');

            if (! empty($retryAfterHeader)) {
                $retryAfter = is_numeric($retryAfterHeader)
                    ? (int) $retryAfterHeader
                    : max(0, strtotime($retryAfterHeader) - time());
            }
        }

        $this->logWarning("HTTP {$status} del Portal Judicial — Max retries reached", [
            'context' => $context,
            'retry_after' => $retryAfter,
        ]);

        $key = $status === 403 ? 'process.api_forbidden' : 'process.api_rate_limit';

        throw new ApiForbiddenOrRateLimitException(
            __($key, ['context' => $context]),
            $retryAfter,
        );
    }

    /**
     * Detects cURL proxy connection errors and throws ApiProxyFailureException.
     */
    private function throwIfProxyFailure(Throwable $th, string $context): void
    {
        $message = $th->getMessage();

        if (! str_contains($message, 'cURL error')) {
            return;
        }

        $this->logWarning("Proxy fatal error — {$message}", [
            'context' => $context,
        ]);

        throw new ApiProxyFailureException(
            "Proxy curl error on {$context}: {$message}. Max retries reached."
        );
    }

    private function logInfo(string $message, array $context = []): void
    {
        Log::channel(config('judicial-branch.log_channel', 'process_import'))
            ->info("[JudicialBranch] {$message}", $context);
    }

    private function logWarning(string $message, array $context = []): void
    {
        Log::channel(config('judicial-branch.log_channel', 'process_import'))
            ->warning("[JudicialBranch] {$message}", $context);
    }

    private function logError(string $message, Throwable $th): void
    {
        Log::channel(config('judicial-branch.log_channel', 'process_import'))
            ->error("[JudicialBranch] {$message}", [
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'message' => $th->getMessage(),
            ]);
    }
}

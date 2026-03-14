<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
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
    ];

    /**
     * Seed derivado del número de radicado.
     * Garantiza que las 4 peticiones del mismo radicado usen el mismo User-Agent,
     * manteniendo coherencia de huella de navegador ante Cloudflare.
     */
    private string $radicadoSeed = '';

    /**
     * Cookie jar compartido entre las 4 peticiones del mismo radicado.
     * Permite que cf_clearance y otras cookies de sesión persistan durante
     * todo el ciclo de vida del servicio, evitando re-desafíos de Cloudflare.
     */
    private CookieJar $cookieJar;

    public function __construct(
        private readonly ProxyPoolService $proxyPool,
    ) {
        $this->cookieJar = new CookieJar;
    }

    /**
     * Sets the radicado seed for this service instance.
     * Call this once per job with the process number so all 4 requests
     * of the same radicado share the same User-Agent fingerprint.
     */
    public function withSeed(string $seed): static
    {
        $this->radicadoSeed = $seed;

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
            $this->throttle();

            $baseUrl = config('judicial-branch.api_url').'/Procesos/Consulta/NumeroRadicacion';
            $allProcesses = [];
            $currentPage = 1;
            $totalPages = 1;

            do {
                $params = [
                    'numero' => $code,
                    'SoloActivos' => 'false',
                    'pagina' => $currentPage,
                ];

                $endpoint = "{$baseUrl}?".http_build_query($params);

                $httpResponse = $this->buildHttpClient()->get($endpoint);

                $this->throwIfForbiddenOrRateLimit($httpResponse->status(), 'fetchProcesses', $httpResponse);

                $response = $httpResponse->json();
                if (! is_array($response)) {
                    $response = [];
                }

                if (isset($response['procesos'])) {
                    $allProcesses = array_merge($allProcesses, $response['procesos']);
                }

                if (isset($response['paginacion'])) {
                    $totalPages = $response['paginacion']['cantidadPaginas'];
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
            $this->throwIfProxyFailure($th, 'fetchProcesses');

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
            $this->throttle();

            $endpoint = config('judicial-branch.api_url')."/Proceso/Detalle/{$processId}";

            $httpResponse = $this->buildHttpClient()->get($endpoint);

            $this->throwIfForbiddenOrRateLimit($httpResponse->status(), 'fetchDetailProcess', $httpResponse);

            $data = $httpResponse->json() ?? [];

        } catch (ApiForbiddenOrRateLimitException|ApiProxyFailureException $e) {
            throw $e;
        } catch (Throwable $th) {
            $this->throwIfProxyFailure($th, 'fetchDetailProcess');

            $isSuccessful = false;

            $this->logError('Error fetching process detail', $th);
        }

        return (object) ['isSuccessful' => $isSuccessful, 'data' => $data];
    }

    /**
     * Fetches all actions for a specific process, handling pagination.
     *
     * @param  int  $processId  Unique ID of the process.
     * @return object{isSuccessful: bool, data: array<mixed>}
     */
    public function fetchActionByProcess(int $processId): object
    {
        $data = [];
        $isSuccessful = true;

        try {
            $this->throttle();

            $baseUrl = config('judicial-branch.api_url')."/Proceso/Actuaciones/{$processId}";
            $allActions = [];
            $currentPage = 1;
            $totalPages = 1;

            do {
                $params = ['pagina' => $currentPage];
                $endpoint = "{$baseUrl}?".http_build_query($params);

                $httpResponse = $this->buildHttpClient()->get($endpoint);

                $this->throwIfForbiddenOrRateLimit($httpResponse->status(), 'fetchActionByProcess', $httpResponse);

                $response = $httpResponse->json();
                if (! is_array($response)) {
                    $response = [];
                }

                if (isset($response['actuaciones'])) {
                    $allActions = array_merge($allActions, $response['actuaciones']);
                }

                if (isset($response['paginacion'])) {
                    $totalPages = $response['paginacion']['cantidadPaginas'];
                }

                $currentPage++;
            } while ($currentPage <= $totalPages);

            $data = $allActions;

        } catch (ApiForbiddenOrRateLimitException|ApiProxyFailureException $e) {
            throw $e;
        } catch (Throwable $th) {
            $this->throwIfProxyFailure($th, 'fetchActionByProcess');

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
            $this->throttle();

            $baseUrl = config('judicial-branch.api_url')."/Proceso/Sujetos/{$processId}";
            $allSubjects = [];
            $currentPage = 1;
            $totalPages = 1;

            do {
                $params = ['pagina' => $currentPage];
                $endpoint = "{$baseUrl}?".http_build_query($params);

                $httpResponse = $this->buildHttpClient()->get($endpoint);

                $this->throwIfForbiddenOrRateLimit($httpResponse->status(), 'fetchSubjectsByProcess', $httpResponse);

                $response = $httpResponse->json();
                if (! is_array($response)) {
                    $response = [];
                }

                if (isset($response['sujetos'])) {
                    $allSubjects = array_merge($allSubjects, $response['sujetos']);
                }

                if (isset($response['paginacion'])) {
                    $totalPages = $response['paginacion']['cantidadPaginas'];
                }

                $currentPage++;
            } while ($currentPage <= $totalPages);

            $data = $allSubjects;

        } catch (ApiForbiddenOrRateLimitException|ApiProxyFailureException $e) {
            throw $e;
        } catch (Throwable $th) {
            $this->throwIfProxyFailure($th, 'fetchSubjectsByProcess');

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
     *  - User-Agent determinístico por radicado (mismo UA en las 4 peticiones)
     *  - Cabeceras humanas realistas para reducir huella ante Cloudflare
     *  - CookieJar compartido para persistir cf_clearance entre peticiones
     *  - Proxy residencial rotativo de Webshare (http://user:pass@p.webshare.io:80)
     */
    private function buildHttpClient(): PendingRequest
    {
        $proxyEnabled = config('judicial-branch.proxy.enabled', false);

        $timeout = $proxyEnabled
            ? (int) config('judicial-branch.proxy.timeout', 20)
            : (int) config('judicial-branch.timeout_seconds', 60);

        $client = Http::timeout($timeout)
            ->withHeaders([
                'User-Agent'       => $this->resolveUserAgent(),
                'Accept'           => 'application/json, text/plain, */*',
                'Accept-Language'  => 'es-CO,es;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding'  => 'gzip, deflate, br',
                'Connection'       => 'keep-alive',
                'Sec-Fetch-Dest'   => 'empty',
                'Sec-Fetch-Mode'   => 'cors',
                'Sec-Fetch-Site'   => 'same-origin',
                'Sec-Ch-Ua-Mobile' => '?0',
                'DNT'              => '1',
            ])
            ->withOptions(['cookies' => $this->cookieJar]);

        if ($proxyEnabled) {
            $proxyUrl = $this->proxyPool->next();

            if ($proxyUrl !== null) {
                $client = $client->withOptions([
                    'proxy'   => $proxyUrl,
                    'cookies' => $this->cookieJar,
                ]);

                $this->logInfo('Using Webshare rotating residential proxy');
            } else {
                $this->logInfo('Proxy credentials not set — using direct connection');
            }
        } else {
            $this->logInfo('Using direct connection (proxy disabled)');
        }

        return $client;
    }

    /**
     * Selects a User-Agent deterministically from the radicado seed so that
     * all 4 requests of the same radicado always present the same browser
     * fingerprint to Cloudflare. Falls back to random when no seed is set.
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
     * Paces HTTP calls per worker using a random jitter delay (human-like).
     *
     * With proxy enabled: random delay between call_delay_min_ms and
     * call_delay_max_ms (default 1500–3500 ms) to emulate human reading time.
     * Without proxy: Laravel RateLimiter enforces a per-minute cap.
     */
    private function throttle(): void
    {
        if (config('judicial-branch.proxy.enabled', false)) {
            $minMs = (int) config('judicial-branch.proxy.call_delay_min_ms', 1500);
            $maxMs = (int) config('judicial-branch.proxy.call_delay_max_ms', 3500);

            // Ensure valid range even if config values are inverted or equal
            if ($minMs >= $maxMs) {
                $maxMs = $minMs + 1000;
            }

            $jitterMs = random_int($minMs, $maxMs);

            $this->logInfo('Throttle jitter applied', ['delay_ms' => $jitterMs]);

            Sleep::usleep($jitterMs * 1000);

            return;
        }

        $key = 'judicial-api-http-calls';
        $limit = (int) config('judicial-branch.rate_limit_per_minute', 8);
        $sleepSeconds = (int) ceil(60 / max(1, $limit));

        while (RateLimiter::tooManyAttempts($key, $limit)) {
            Sleep::sleep($sleepSeconds);
        }

        RateLimiter::hit($key, 60);
    }

    /**
     * Throws ApiForbiddenOrRateLimitException on HTTP 403 or 429.
     *
     * Reads the Retry-After response header (if present) and passes it into
     * the exception so the job can honour the server-mandated wait time.
     *
     * With proxy pool: 403 = that specific IP is blocked by Rama Judicial.
     * The next retry will use a different IP (different seed position).
     * We do NOT mark the proxy as failed — 403 is temporary per IP, not a
     * dead proxy. Only cURL errors 7/28/56 justify permanent deactivation.
     */
    private function throwIfForbiddenOrRateLimit(int $status, string $context, ?Response $httpResponse = null): void
    {
        if ($status !== 403 && $status !== 429) {
            return;
        }

        $proxyMode = config('judicial-branch.proxy.enabled', false)
            ? ('proxy pool ['.$this->proxyPool->count().' IPs]')
            : 'direct connection';

        $retryAfter = null;

        if ($httpResponse !== null) {
            $retryAfterHeader = $httpResponse->header('Retry-After');

            if ($retryAfterHeader !== '' && $retryAfterHeader !== null) {
                $retryAfter = is_numeric($retryAfterHeader)
                    ? (int) $retryAfterHeader
                    : (int) max(0, strtotime($retryAfterHeader) - time());
            }
        }

        $this->logWarning("HTTP {$status} from Rama Judicial", [
            'context'     => $context,
            'proxy_mode'  => $proxyMode,
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
     *
     * With Webshare rotating residential, there are no individual IPs to
     * deactivate. The exception signals the job to retry — on the next attempt
     * Webshare will automatically assign a different residential exit IP.
     *
     * Detected errors:
     *   - cURL 7  (CURLE_COULDNT_CONNECT): proxy gateway unreachable.
     *   - cURL 28 (CURLE_OPERATION_TIMEDOUT): proxy timed out.
     *   - cURL 56 (CURLE_RECV_ERROR): proxy tunnel failed mid-response.
     */
    private function throwIfProxyFailure(Throwable $th, string $context): void
    {
        if (! config('judicial-branch.proxy.enabled', false)) {
            return;
        }

        $message = $th->getMessage();

        $isCurlError7  = str_contains($message, 'cURL error 7');
        $isCurlError28 = str_contains($message, 'cURL error 28');
        $isCurlError56 = str_contains($message, 'cURL error 56');

        if (! $isCurlError7 && ! $isCurlError28 && ! $isCurlError56) {
            return;
        }

        $curlCode = match (true) {
            $isCurlError7  => 7,
            $isCurlError28 => 28,
            default        => 56,
        };

        $label = match ($curlCode) {
            7  => 'proxy gateway unreachable (CURLE_COULDNT_CONNECT)',
            28 => 'proxy timeout (CURLE_OPERATION_TIMEDOUT)',
            56 => 'proxy tunnel failed (CURLE_RECV_ERROR)',
        };

        $this->proxyPool->markFailed('rotating-residential');

        $this->logWarning("Proxy error — {$label} — Webshare will rotate to a new IP on retry", [
            'context'    => $context,
            'curl_error' => $curlCode,
        ]);

        throw new ApiProxyFailureException(
            "Proxy error on {$context}: {$label}. Webshare will assign a new residential IP on retry."
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

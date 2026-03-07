<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services;

use Illuminate\Http\Client\PendingRequest;
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
    /** Proxy URL used in the current HTTP call, for failure reporting. */
    private ?string $currentProxy = null;

    /** Seed used to select a proxy — set per job via withSeed(). */
    private string $proxySeed = '';

    public function __construct(
        private readonly ProxyPoolService $proxyPool,
    ) {}

    /**
     * Sets the proxy seed for this service instance.
     * Call this once per job with a unique value (e.g. processNumber:attempt)
     * so each job/retry maps to a different IP in the pool.
     */
    public function withSeed(string $seed): static
    {
        $this->proxySeed = $seed;

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

                $this->throwIfForbiddenOrRateLimit($httpResponse->status(), 'fetchProcesses');

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

            $this->throwIfForbiddenOrRateLimit($httpResponse->status(), 'fetchDetailProcess');

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

                $this->throwIfForbiddenOrRateLimit($httpResponse->status(), 'fetchActionByProcess');

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

                $this->throwIfForbiddenOrRateLimit($httpResponse->status(), 'fetchSubjectsByProcess');

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
     * Builds an HTTP client with timeout and, when proxy is enabled, the next
     * proxy from the pool selected by the current seed.
     */
    private function buildHttpClient(): PendingRequest
    {
        $proxyEnabled = config('judicial-branch.proxy.enabled', false);

        $timeout = $proxyEnabled
            ? (int) config('judicial-branch.proxy.timeout', 20)
            : (int) config('judicial-branch.timeout_seconds', 60);

        $client = Http::timeout($timeout)->withHeaders([
            'Content-Type' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        ]);

        if ($proxyEnabled) {
            $this->currentProxy = $this->proxyPool->next($this->proxySeed);

            if ($this->currentProxy !== null) {
                $client = $client->withOptions(['proxy' => $this->currentProxy]);

                $this->logInfo('Using proxy (round-robin)', [
                    'pool_active' => $this->proxyPool->count(),
                ]);
            } else {
                $this->logInfo('Proxy pool empty — using direct connection');
            }
        } else {
            $this->logInfo('Using direct connection (proxy disabled)');
        }

        return $client;
    }

    /**
     * Paces HTTP calls per worker using a fixed sleep.
     * With proxy enabled: 500ms between calls (configurable).
     * Without proxy: Laravel RateLimiter enforces a per-minute cap.
     */
    private function throttle(): void
    {
        if (config('judicial-branch.proxy.enabled', false)) {
            $delayMs = (int) config('judicial-branch.proxy.call_delay_ms', 500);

            if ($delayMs > 0) {
                Sleep::usleep($delayMs * 1000);
            }

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
     * With proxy pool: 403 = that specific IP is blocked by Rama Judicial.
     * The next retry will use a different IP (different seed position).
     * We do NOT mark the proxy as failed — 403 is temporary per IP, not a
     * dead proxy. Only cURL errors 7/28/56 justify permanent deactivation.
     */
    private function throwIfForbiddenOrRateLimit(int $status, string $context): void
    {
        if ($status === 403 || $status === 429) {
            $proxyMode = config('judicial-branch.proxy.enabled', false)
                ? ('proxy pool ['.$this->proxyPool->count().' IPs]')
                : 'direct connection';

            $this->logWarning("HTTP {$status} from Rama Judicial", [
                'context' => $context,
                'proxy_mode' => $proxyMode,
            ]);

            $key = $status === 403 ? 'process.api_forbidden' : 'process.api_rate_limit';

            throw new ApiForbiddenOrRateLimitException(
                __($key, ['context' => $context])
            );
        }
    }

    /**
     * Converts cURL proxy errors into ApiProxyFailureException and marks the
     * proxy as permanently inactive in the pool.
     *
     * Detected errors:
     *   - cURL 7  (CURLE_COULDNT_CONNECT): proxy is dead or unreachable.
     *   - cURL 28 (CURLE_OPERATION_TIMEDOUT): proxy timed out.
     *   - cURL 56 (CURLE_RECV_ERROR): proxy returned 502/503 mid-tunnel.
     */
    private function throwIfProxyFailure(Throwable $th, string $context): void
    {
        if (! config('judicial-branch.proxy.enabled', false)) {
            return;
        }

        $message = $th->getMessage();

        $isCurlError7 = str_contains($message, 'cURL error 7');
        $isCurlError28 = str_contains($message, 'cURL error 28');
        $isCurlError56 = str_contains($message, 'cURL error 56');

        if (! $isCurlError7 && ! $isCurlError28 && ! $isCurlError56) {
            return;
        }

        $curlCode = match (true) {
            $isCurlError7 => 7,
            $isCurlError28 => 28,
            default => 56,
        };

        $label = match ($curlCode) {
            7 => 'proxy unreachable (CURLE_COULDNT_CONNECT)',
            28 => 'proxy timeout (CURLE_OPERATION_TIMEDOUT)',
            56 => 'proxy tunnel failed 502/503 (CURLE_RECV_ERROR)',
        };

        if ($this->currentProxy !== null) {
            $this->proxyPool->markFailed($this->currentProxy);
        }

        $this->logWarning("Proxy failure — {$label}, proxy marked inactive, will retry with next IP", [
            'context' => $context,
            'curl_error' => $curlCode,
            'pool_active' => $this->proxyPool->count(),
        ]);

        throw new ApiProxyFailureException(
            "Proxy failure on {$context}: {$label}. A different IP will be used on retry."
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

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
    public function __construct(
        private readonly ProxyPoolService $proxyPool,
    ) {}

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
     * Builds an HTTP client pre-configured with timeout and, when proxy is
     * enabled, a randomly selected proxy from the pool.
     *
     * When proxies are active the timeout is capped at 15 s: datacenter proxies
     * typically fail within 10 s anyway (cURL 28), so waiting 60 s only delays
     * the retry unnecessarily.
     */
    private function buildHttpClient(): PendingRequest
    {
        $proxyEnabled = config('judicial-branch.proxy.enabled', false);

        $timeout = $proxyEnabled
            ? (int) config('judicial-branch.proxy_timeout_seconds', 15)
            : (int) config('judicial-branch.timeout_seconds', 60);

        $client = Http::timeout($timeout)->withHeaders([
            'Content-Type' => 'application/json',
        ]);

        $proxy = $this->proxyPool->next();

        if ($proxy !== null) {
            $client = $client->withOptions(['proxy' => $proxy]);

            $this->logInfo('Using proxy', [
                'proxy' => $proxy,
                'pool_count' => $this->proxyPool->count(),
            ]);
        } else {
            $this->logInfo('Using direct connection (proxy disabled or pool empty)');
        }

        return $client;
    }

    /**
     * Applies internal rate limiting when proxies are disabled.
     *
     * When proxies are enabled each request exits from a different IP, so the
     * per-IP rate limit of Rama Judicial does not apply and the throttle is
     * skipped. Instead, a fixed 3-second sleep is used between requests to give
     * the database enough time to persist the previous result before the next
     * job is picked up.
     *
     * Without proxies, a single worker is required so the sleep-based bucket
     * works correctly.
     */
    private function throttle(): void
    {
        if (config('judicial-branch.proxy.enabled', false)) {
            $delaySecs = (int) config('process-import.delay_between_radicados_seconds', 3);

            if ($delaySecs > 0) {
                Sleep::sleep($delaySecs);
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
     * Throws an exception when Rama Judicial returns 403 or 429 so the job
     * can retry with exponential back-off instead of silently failing.
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
     * Converts cURL proxy errors into ApiProxyFailureException so the job
     * retries immediately and array_rand() picks a different IP next time.
     *
     * Detected errors:
     *   - cURL 7  (CURLE_COULDNT_CONNECT): proxy is dead or blocked.
     *   - cURL 28 (CURLE_OPERATION_TIMEDOUT): proxy timed out before the
     *     server responded (typically ~10 s with datacenter proxies).
     *
     * Only triggers when proxy is enabled; without proxy these errors indicate
     * a real connectivity problem and should be treated as generic failures.
     */
    private function throwIfProxyFailure(Throwable $th, string $context): void
    {
        if (! config('judicial-branch.proxy.enabled', false)) {
            return;
        }

        $message = $th->getMessage();

        $isCurlError7 = str_contains($message, 'cURL error 7');
        $isCurlError28 = str_contains($message, 'cURL error 28');

        if (! $isCurlError7 && ! $isCurlError28) {
            return;
        }

        $curlCode = $isCurlError7 ? 7 : 28;
        $label = $isCurlError7 ? 'proxy dead (CURLE_COULDNT_CONNECT)' : 'proxy timeout (CURLE_OPERATION_TIMEDOUT)';

        $this->logWarning("Proxy failure — {$label}, will retry with different IP", [
            'context' => $context,
            'curl_error' => $curlCode,
            'pool_count' => $this->proxyPool->count(),
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

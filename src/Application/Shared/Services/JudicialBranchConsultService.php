<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Exceptions\ApiEmptyProcessesException;
use Src\Application\Shared\Exceptions\ApiForbiddenOrRateLimitException;
use Throwable;

class JudicialBranchConsultService
{
    /**
     * Fetches a list of processes by filing code.
     *
     * @param  string  $code  Filing code (radicado number).
     * @return object Response with status and process list.
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

            do {
                $params = [
                    'numero' => $code,
                    'SoloActivos' => 'false',
                    'pagina' => $currentPage,
                ];

                $endpoint = "{$baseUrl}?".http_build_query($params);
                $timeout = (int) config('judicial-branch.timeout_seconds', 60);

                $httpResponse = Http::timeout($timeout)->withHeaders([
                    'Content-Type' => 'application/json',
                ])->get($endpoint);

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

            // 200 con procesos vacíos: fallo definitivo, no reintentar (el usuario puede verificar en la Rama Judicial).
            if (empty($allProcesses)) {
                throw new ApiEmptyProcessesException(__('process.api_empty_processes'));
            }
        } catch (ApiEmptyProcessesException|ApiForbiddenOrRateLimitException $e) {
            throw $e;
        } catch (Throwable $th) {
            $isSuccessful = false;

            Log::channel('filings_process')->error('Error processing filing ', [
                'class' => $th->getFile(),
                'line' => $th->getLine(),
                'message' => $th->getMessage(),
            ]);
        }

        return (object) ['isSuccessful' => $isSuccessful, 'data' => $data];
    }

    /**
     * Lanza excepción si el status es 403 o 429 para que el job reintente (no se trate como "sin procesos").
     */
    private function throwIfForbiddenOrRateLimit(int $status, string $context): void
    {
        if ($status === 403) {
            throw new ApiForbiddenOrRateLimitException(
                __('process.api_forbidden', ['context' => $context])
            );
        }
        if ($status === 429) {
            throw new ApiForbiddenOrRateLimitException(
                __('process.api_rate_limit', ['context' => $context])
            );
        }
    }

    /**
     * Fetches detailed information of a specific process.
     *
     * @param  int  $processId  Unique ID of the process.
     * @return object Response with status and detailed data.
     */
    public function fetchDetailProcess(int $processId): object
    {
        $data = [];
        $isSuccessful = true;

        try {
            $endpoint = config('judicial-branch.api_url')."/Proceso/Detalle/{$processId}";
            $timeout = (int) config('judicial-branch.timeout_seconds', 60);

            $httpResponse = Http::timeout($timeout)->withHeaders([
                'Content-Type' => 'application/json',
            ])->get($endpoint);

            $this->throwIfForbiddenOrRateLimit($httpResponse->status(), 'fetchDetailProcess');

            $data = $httpResponse->json() ?? [];

        } catch (ApiForbiddenOrRateLimitException $e) {
            throw $e;
        } catch (Throwable $th) {
            $isSuccessful = false;

            Log::channel('filings_process')->error('Error processing process ', [
                'class' => $th->getFile(),
                'line' => $th->getLine(),
                'message' => $th->getMessage(),
            ]);
        }

        return (object) ['isSuccessful' => $isSuccessful, 'data' => $data];
    }

    /**
     * Fetches all actions for a specific process, handling pagination.
     *
     * @param  int  $processId  Unique ID of the process.
     * @return object Response with status and all actions data.
     */
    public function fetchActionByProcess(int $processId): object
    {
        $data = [];
        $isSuccessful = true;

        try {
            $baseUrl = config('judicial-branch.api_url')."/Proceso/Actuaciones/{$processId}";
            $allActions = [];
            $currentPage = 1;
            $totalPages = 1;

            do {
                $params = [
                    'pagina' => $currentPage,
                ];

                $endpoint = "{$baseUrl}?".http_build_query($params);
                $timeout = (int) config('judicial-branch.timeout_seconds', 60);

                $httpResponse = Http::timeout($timeout)->withHeaders([
                    'Content-Type' => 'application/json',
                ])->get($endpoint);

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

        } catch (ApiForbiddenOrRateLimitException $e) {
            throw $e;
        } catch (Throwable $th) {
            $isSuccessful = false;

            Log::channel('filings_process')->error('Error processing actions ', [
                'class' => $th->getFile(),
                'line' => $th->getLine(),
                'message' => $th->getMessage(),
            ]);
        }

        return (object) ['isSuccessful' => $isSuccessful, 'data' => $data];
    }

    /**
     * Fetches all subjects for a specific process, handling pagination.
     *
     * @param  int  $processId  Unique ID of the process.
     * @return object Response with status and all subjects data.
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

            do {
                $params = [
                    'pagina' => $currentPage,
                ];

                $endpoint = "{$baseUrl}?".http_build_query($params);
                $timeout = (int) config('judicial-branch.timeout_seconds', 60);

                $httpResponse = Http::timeout($timeout)->withHeaders([
                    'Content-Type' => 'application/json',
                ])->get($endpoint);

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

        } catch (ApiForbiddenOrRateLimitException $e) {
            throw $e;
        } catch (Throwable $th) {
            $isSuccessful = false;

            Log::channel('filings_process')->error('Error processing subjects ', [
                'class' => $th->getFile(),
                'line' => $th->getLine(),
                'message' => $th->getMessage(),
            ]);
        }

        return (object) ['isSuccessful' => $isSuccessful, 'data' => $data];
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\CourtDirectory;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Src\Application\Shared\Services\ProxyPoolService;

/**
 * HTTP del directorio. Cuando el proxy residencial de la Rama está activo,
 * las peticiones salen por él: Azure Front Door bloquea la IP del datacenter.
 */
class CourtDirectoryHttpClient
{
    private const PROXY_SEED = 'court-directory';

    private bool $usedProxy = false;

    public function __construct(
        private readonly ProxyPoolService $proxyPool,
    ) {}

    /**
     * @param  array<string, string>  $headers
     */
    public function get(string $url, array $headers = []): Response
    {
        return $this->send(
            fn (PendingRequest $pending): Response => $pending->withHeaders($headers)->get($url),
        );
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    public function post(string $url, array $body, array $headers = []): Response
    {
        return $this->send(
            fn (PendingRequest $pending): Response => $pending->withHeaders($headers)->post($url, $body),
        );
    }

    /**
     * @param  callable(PendingRequest): Response  $attempt
     */
    private function send(callable $attempt): Response
    {
        $response = $attempt($this->pending());

        if (! $this->shouldRetry($response)) {
            return $response;
        }

        $this->proxyPool->markFailed(self::PROXY_SEED);

        return $attempt($this->pending());
    }

    private function pending(): PendingRequest
    {
        $pending = Http::timeout((int) config('court-directory.timeout'))
            ->withUserAgent((string) config('court-directory.user_agent'));

        $proxyUrl = $this->proxyPool->next(self::PROXY_SEED);
        $this->usedProxy = is_string($proxyUrl) && $proxyUrl !== '';
        if ($this->usedProxy) {
            return $pending->withOptions(['proxy' => $proxyUrl]);
        }

        return $pending;
    }

    private function shouldRetry(Response $response): bool
    {
        return $this->usedProxy && in_array($response->status(), [403, 502], true);
    }
}

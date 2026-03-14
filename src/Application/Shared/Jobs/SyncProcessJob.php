<?php

declare(strict_types=1);

namespace Src\Application\Shared\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Random\RandomException;
use Src\Application\Shared\Exceptions\ApiForbiddenOrRateLimitException;
use Src\Application\Shared\Exceptions\ApiProxyFailureException;
use Src\Application\Shared\Services\Process\ProcessSyncService;
use Throwable;

class SyncProcessJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $tries = 3;

    /** @var int */
    public $timeout = 120;

    public function __construct(
        public string $processNumber
    ) {
        $config = config('judicial-sync.jobs.sync_process', []);
        $this->queue = $config['queue'] ?? 'judicial-sync';
        $this->tries = $config['tries'] ?? 3;
        $this->timeout = $config['timeout'] ?? 120;
        if (! empty($config['connection'])) {
            $this->connection = $config['connection'];
        }
    }

    public static function fromProcessNumber(string $processNumber): self
    {
        return new self($processNumber);
    }

    /**
     * @throws Throwable
     * @throws RandomException
     */
    public function handle(ProcessSyncService $syncService): void
    {
        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        try {
            $syncService->syncByProcessNumber($this->processNumber);

        } catch (ApiForbiddenOrRateLimitException $e) {
            $delay = $this->resolveRateLimitDelay($e);

            Log::channel($channel)->warning('SyncProcessJob rate-limited, will retry', [
                'process_number'  => $this->processNumber,
                'attempt'         => $this->attempts(),
                'release_seconds' => $delay,
                'retry_after'     => $e->retryAfter,
                'message'         => $e->getMessage(),
            ]);

            $this->release($delay);

        } catch (ApiProxyFailureException $e) {
            $delay = (int) config('judicial-sync.retry_release_seconds_for_proxy_failure', 5);

            Log::channel($channel)->warning('SyncProcessJob proxy failure, will retry with next IP', [
                'process_number'  => $this->processNumber,
                'attempt'         => $this->attempts(),
                'release_seconds' => $delay,
                'message'         => $e->getMessage(),
            ]);

            $this->release($delay);

        } catch (Throwable $e) {
            Log::channel($channel)->error('SyncProcessJob failed', [
                'process_number' => $this->processNumber,
                'attempt'        => $this->attempts(),
                'message'        => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Resolves the release delay for a 403/429 response.
     *
     * Priority order:
     *  1. Retry-After header value (server-mandated, exact seconds).
     *  2. Exponential backoff: (2 ** attempt) + random_int(1, 3) seconds.
     *
     * @throws RandomException
     */
    private function resolveRateLimitDelay(ApiForbiddenOrRateLimitException $e): int
    {
        if ($e->retryAfter !== null && $e->retryAfter > 0) {
            return $e->retryAfter;
        }

        $proxyEnabled = config('judicial-branch.proxy.enabled', false);
        $base = $proxyEnabled
            ? (int) config('judicial-sync.retry_release_seconds_for_rate_limit_proxy', 5)
            : (int) config('judicial-sync.retry_release_seconds_for_rate_limit', 60);

        $exponential = (int) min(3600, max($base, 2 ** $this->attempts()));

        return $exponential + random_int(1, 3);
    }
}

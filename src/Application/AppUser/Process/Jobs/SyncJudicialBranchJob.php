<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Random\RandomException;
use Src\Application\AppUser\Process\Services\RegisterProcessService;
use Src\Application\Shared\Exceptions\ApiEmptyProcessesException;
use Src\Application\Shared\Exceptions\ApiForbiddenOrRateLimitException;
use Src\Application\Shared\Exceptions\ApiProxyFailureException;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Notifications\ProcessDataImportedNotification;
use Src\Domain\Notification\Notifications\ProcessImportFailedNotification;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessRegistrationLog;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class SyncJudicialBranchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    /** Retries cover Portal/proxy transients; same door as admin ImportRadicadoJob. */
    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 15, 30, 60];

    public function __construct(
        public string $processNumber,
        public string $organizationId,
        public AppUser $appUser,
        public ?ProcessLawyerRole $lawyerRole = null
    ) {
        $config = config('process-import.jobs.import_radicado', []);
        $this->tries = (int) ($config['tries'] ?? 30);
        $this->timeout = (int) ($config['timeout'] ?? 600);
    }

    /**
     * @throws Throwable
     */
    public function handle(RegisterProcessService $registerProcessService): void
    {
        try {
            $seed = $this->processNumber.':'.$this->attempts();

            $result = $registerProcessService->handle(
                $this->processNumber,
                $this->organizationId,
                $this->lawyerRole,
                $seed,
                $this->appUser->id
            );

            $process = $result->getFirstProcess();

            if ($process instanceof Process) {
                $this->updateLogStatus('success');

                $this->appUser->notify(new ProcessDataImportedNotification($process));

                if (config('ia-rag.enabled')) {
                    dispatch(new GenerateProcessAiSummaryJob($process, $this->organizationId, $this->appUser))
                        ->onQueue(config('ia-rag.queues.ai'));
                }
            } else {
                $this->updateLogStatus('failed', 'No process was imported.');
                $this->appUser->notify(new ProcessImportFailedNotification(
                    $this->processNumber,
                    'No process was imported.'
                ));
            }
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Final failure after retries are exhausted (or non-retryable path threw).
     */
    public function failed(?Throwable $e = null): void
    {
        $message = $e instanceof Throwable
            ? $e->getMessage()
            : __('process.import_job_max_attempts_exceeded');

        $this->updateLogStatus('failed', $message);
        $this->appUser->notify(new ProcessImportFailedNotification($this->processNumber, $message));
    }

    /**
     * @throws Throwable
     * @throws RandomException
     */
    private function handleException(Throwable $e): void
    {
        [$releaseSeconds, $maxAttempts] = $this->resolveRetryConfig($e);

        if ($this->attempts() <= $maxAttempts) {
            $this->release($releaseSeconds);

            return;
        }

        throw $e;
    }

    /**
     * @return array{int, int}
     *
     * @throws RandomException
     */
    private function resolveRetryConfig(Throwable $e): array
    {
        if ($e instanceof ApiProxyFailureException) {
            return [
                (int) config('process-import.retry_release_seconds_for_proxy_failure', 5),
                (int) config('process-import.retry_max_attempts_for_proxy_failure', 10),
            ];
        }

        if ($e instanceof ApiEmptyProcessesException) {
            return [
                (int) config('process-import.retry_release_seconds_for_empty', 120),
                (int) config('process-import.retry_max_attempts_for_empty', 3),
            ];
        }

        if ($e instanceof ApiForbiddenOrRateLimitException) {
            $maxAttempts = (int) config('process-import.retry_max_attempts_for_rate_limit', 10);

            if ($e->retryAfter !== null && $e->retryAfter > 0) {
                return [$e->retryAfter, $maxAttempts];
            }

            if (config('judicial-branch.proxy.enabled', false)) {
                $base = (int) config('process-import.retry_release_seconds_for_rate_limit_proxy', 5);

                return [$this->exponentialBackoff($this->attempts(), $base), $maxAttempts];
            }

            $base = (int) config('process-import.retry_release_seconds_for_rate_limit', 60);

            return [$this->exponentialBackoff($this->attempts(), $base), $maxAttempts];
        }

        if ($this->isNotFoundError($e)) {
            return [
                (int) config('process-import.retry_release_seconds_for_not_found', 300),
                (int) config('process-import.retry_max_attempts_for_not_found', 10),
            ];
        }

        return [
            (int) config('process-import.retry_release_seconds', 120),
            (int) config('process-import.retry_max_attempts', 2),
        ];
    }

    /**
     * @throws RandomException
     */
    private function exponentialBackoff(int $attempt, int $base = 5): int
    {
        $exponential = (int) min(3600, max($base, 2 ** $attempt));

        return $exponential + random_int(1, 3);
    }

    private function isNotFoundError(Throwable $e): bool
    {
        $message = $e->getMessage();

        if (str_contains($message, 'no existe') && str_contains($message, 'Portal Judicial')) {
            return true;
        }

        if (str_contains($message, 'does not exist') && str_contains($message, 'Portal Judicial')) {
            return true;
        }

        return $e instanceof NotFoundHttpException;
    }

    private function updateLogStatus(string $status, ?string $error = null): void
    {
        ProcessRegistrationLog::query()
            ->where('process_number', $this->processNumber)
            ->where('organization_id', $this->organizationId)
            ->where('app_user_id', $this->appUser->id)
            ->where('status', 'pending')
            ->latest()
            ->first()
            ?->update([
                'status' => $status,
                'error' => $error,
            ]);
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
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

    /** @var int|array<int> */
    public $backoff = 60;

    /** @var int */
    public $timeout = 120;

    public function __construct(
        public string $processNumber
    ) {
        $config = config('judicial-sync.jobs.sync_process', []);
        $this->queue = $config['queue'] ?? 'judicial-sync';
        $this->tries = $config['tries'] ?? 3;
        $this->backoff = $config['backoff'] ?? 60;
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
     */
    public function handle(ProcessSyncService $syncService): void
    {
        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        try {
            $syncService->syncByProcessNumber($this->processNumber);

        } catch (Throwable $e) {
            Log::channel($channel)->error('SyncProcessJob failed', [
                'process_number' => $this->processNumber,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

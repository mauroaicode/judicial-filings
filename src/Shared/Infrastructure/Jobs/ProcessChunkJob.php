<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Jobs;

use Illuminate\Queue\{
    SerializesModels,
    InteractsWithQueue,
};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Core\BoundedContext\Customer\Process\Application\Services\JudicialProcessChunkService;

class ProcessChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 400;
    public $tries = 3;

    public function __construct(private readonly array $filingNumbers) {
        $this->onQueue(config('queue.queues.process-chunk.queue'));
    }

    public function handle(JudicialProcessChunkService $judicialProcessChunkService): void
    {
        $judicialProcessChunkService->handle($this->filingNumbers);
    }
}

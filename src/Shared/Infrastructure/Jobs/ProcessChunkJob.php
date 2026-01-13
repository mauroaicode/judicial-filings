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

/**
 * Job responsible for processing chunks of filing numbers by consulting
 * the judicial branch API. Performs the complete flow for each filing:
 * collecting filing information, processes, and actions one by one.
 */
class ProcessChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 400;
    public $tries = 3;

    /**
     * Initialize the process chunk job with filing numbers to process
     *
     * @param array $filingNumbers Array of filing numbers to process
     */
    public function __construct(private readonly array $filingNumbers) {
        $this->onQueue(config('queue.queues.process-chunk.queue'));
    }

    /**
     * Execute the job by processing the filing numbers through the judicial process chunk service
     *
     * @param JudicialProcessChunkService $judicialProcessChunkService
     * @return void
     */
    public function handle(JudicialProcessChunkService $judicialProcessChunkService): void
    {
        $judicialProcessChunkService->handle($this->filingNumbers);
    }
}

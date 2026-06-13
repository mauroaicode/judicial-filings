<?php

declare(strict_types=1);

namespace Src\Application\Shared\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Src\Application\Admin\Process\Services\ProcessImportBatchService;

/**
 * Runs once after all ImportRadicadoJob entries for a batch have finished.
 *
 * Laravel Bus::batch()->then() callbacks can fail silently inside the queue worker;
 * this job is dispatched explicitly from each import job when counters reach total_count.
 */
class FinalizeProcessImportBatchJob implements ShouldBeUnique, ShouldQueue
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
        public readonly string $processImportBatchId,
    ) {
        $this->queue = config('process-import.jobs.import_radicado.queue', 'process-import');
    }

    public function uniqueId(): string
    {
        return $this->processImportBatchId;
    }

    public function handle(ProcessImportBatchService $batchService): void
    {
        $batchService->finalize($this->processImportBatchId);
    }
}

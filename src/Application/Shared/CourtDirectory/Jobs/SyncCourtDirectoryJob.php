<?php

declare(strict_types=1);

namespace Src\Application\Shared\CourtDirectory\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Src\Application\Shared\CourtDirectory\Services\SyncCourtDirectoryService;
use Src\Domain\CourtDirectory\Enums\CourtDirectorySyncStatus;
use Src\Domain\CourtDirectory\Models\CourtDirectorySyncRun;

class SyncCourtDirectoryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public readonly string $runId)
    {
        $this->onQueue((string) config('court-directory.queue'));

        $connection = config('court-directory.queue_connection');
        if (is_string($connection) && $connection !== '') {
            $this->onConnection($connection);
        }
    }

    public function uniqueId(): string
    {
        return 'court-directory-sync';
    }

    public function handle(SyncCourtDirectoryService $syncCourtDirectoryService): void
    {
        $run = CourtDirectorySyncRun::query()->find($this->runId);
        if (! $run instanceof CourtDirectorySyncRun) {
            return;
        }

        $run = $syncCourtDirectoryService->handle($run);

        if ($run->status !== CourtDirectorySyncStatus::Succeeded) {
            $this->fail(new RuntimeException($run->error ?? 'La sincronización del directorio falló.'));
        }
    }
}

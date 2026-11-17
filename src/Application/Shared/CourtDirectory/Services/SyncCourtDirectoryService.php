<?php

declare(strict_types=1);

namespace Src\Application\Shared\CourtDirectory\Services;

use Illuminate\Support\Facades\Log;
use Src\Domain\CourtDirectory\Enums\CourtDirectorySyncStatus;
use Src\Domain\CourtDirectory\Models\CourtDirectorySyncRun;
use Throwable;

readonly class SyncCourtDirectoryService
{
    public function __construct(
        private FetchCourtDirectoryRowsService $fetchCourtDirectoryRowsService,
        private UpsertCourtDirectoryEntriesService $upsertCourtDirectoryEntriesService,
    ) {}

    public function handle(CourtDirectorySyncRun $run): CourtDirectorySyncRun
    {
        try {
            $rows = $this->fetchCourtDirectoryRowsService->handle();
            $result = $this->upsertCourtDirectoryEntriesService->handle($run, $rows);

            $run->update([
                'finished_at' => now(),
                'status' => CourtDirectorySyncStatus::Succeeded,
                'rows_fetched' => $result->fetched,
                'rows_upserted' => $result->upserted,
                'rows_marked_inactive' => $result->markedInactive,
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            Log::error('Court directory sync failed', [
                'run_id' => $run->id,
                'error' => $exception->getMessage(),
            ]);

            $run->update([
                'finished_at' => now(),
                'status' => CourtDirectorySyncStatus::Failed,
                'error' => mb_substr($exception->getMessage(), 0, 5000),
            ]);
        }

        return $run->refresh();
    }
}

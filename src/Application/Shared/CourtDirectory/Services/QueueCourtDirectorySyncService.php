<?php

declare(strict_types=1);

namespace Src\Application\Shared\CourtDirectory\Services;

use Src\Application\Shared\CourtDirectory\CourtDirectorySyncException;
use Src\Application\Shared\CourtDirectory\Jobs\SyncCourtDirectoryJob;
use Src\Domain\CourtDirectory\Enums\CourtDirectorySyncStatus;
use Src\Domain\CourtDirectory\Enums\CourtDirectorySyncTrigger;
use Src\Domain\CourtDirectory\Models\CourtDirectorySyncRun;

readonly class QueueCourtDirectorySyncService
{
    public function handle(CourtDirectorySyncTrigger $trigger): CourtDirectorySyncRun
    {
        $alreadyRunning = CourtDirectorySyncRun::query()
            ->where('status', CourtDirectorySyncStatus::Running)
            ->where('started_at', '>=', now()->subHours(2))
            ->exists();

        if ($alreadyRunning) {
            throw new CourtDirectorySyncException('Ya hay una sincronización del directorio en curso.');
        }

        $run = CourtDirectorySyncRun::query()->create([
            'started_at' => now(),
            'trigger' => $trigger,
            'status' => CourtDirectorySyncStatus::Running,
            'rows_fetched' => 0,
            'rows_upserted' => 0,
            'rows_marked_inactive' => 0,
        ]);

        dispatch(new SyncCourtDirectoryJob($run->id));

        return $run->refresh();
    }
}

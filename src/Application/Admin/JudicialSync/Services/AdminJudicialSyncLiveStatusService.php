<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Services;

use Src\Application\Admin\JudicialSync\Resources\JudicialSyncLiveSourceResource;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;

readonly class AdminJudicialSyncLiveStatusService
{
    /**
     * Snapshot of live sync status per known data source (Rama, SAMAI, TYBA…).
     *
     * @return array{has_active: bool, active_count: int, sources: list<array<string, mixed>>, active: list<array<string, mixed>>}
     */
    public function handle(): array
    {
        $sources = [];

        foreach (JudicialSyncDataSource::cases() as $source) {
            $activeRun = $this->latestActiveRun($source);

            $sources[] = $activeRun instanceof JudicialSyncRun
                ? JudicialSyncLiveSourceResource::fromActiveRun($activeRun)
                : JudicialSyncLiveSourceResource::idle($source);
        }

        return JudicialSyncLiveSourceResource::wrapCollection($sources);
    }

    private function latestActiveRun(JudicialSyncDataSource $source): ?JudicialSyncRun
    {
        /** @var JudicialSyncRun|null $run */
        $run = JudicialSyncRun::query()
            ->withQueueBatchStats()
            ->where('judicial_sync_runs.data_source', $source)
            ->whereIn('judicial_sync_runs.status', [
                JudicialSyncRunStatus::Started->value,
                JudicialSyncRunStatus::BatchPending->value,
            ])
            ->where('judicial_sync_runs.started_at', '>=', now()->subHours(8))
            ->latest('judicial_sync_runs.started_at')
            ->orderByDesc('judicial_sync_runs.id')
            ->first();

        return $run;
    }
}

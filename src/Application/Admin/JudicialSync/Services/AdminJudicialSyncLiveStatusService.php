<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Services;

use Illuminate\Database\Eloquent\Builder;
use Src\Application\Admin\JudicialSync\Resources\JudicialSyncLiveSourceResource;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;
use Src\Domain\JudicialSync\QueryBuilders\JudicialSyncRunQueryBuilder;

readonly class AdminJudicialSyncLiveStatusService
{
    /**
     * Snapshot of live sync status per known data source (Rama, SAMAI, TYBA…).
     *
     * @return array{has_active: bool, active_count: int, sources: list<array<string, mixed>>, active: list<array<string, mixed>>}
     */
    public function handle(): array
    {
        $sources = array_map(
            $this->sourceSnapshot(...),
            JudicialSyncDataSource::cases(),
        );

        $organizationRuns = array_map(
            JudicialSyncLiveSourceResource::fromActiveRun(...),
            $this->activeOrganizationRuns(),
        );

        return JudicialSyncLiveSourceResource::wrapCollection($sources, $organizationRuns);
    }

    private function sourceSnapshot(JudicialSyncDataSource $source): JudicialSyncLiveSourceResource
    {
        $activeRun = $this->latestGlobalActiveRun($source);

        if (! $activeRun instanceof JudicialSyncRun) {
            return JudicialSyncLiveSourceResource::idle($source);
        }

        return JudicialSyncLiveSourceResource::fromActiveRun($activeRun);
    }

    private function latestGlobalActiveRun(JudicialSyncDataSource $source): ?JudicialSyncRun
    {
        /** @var JudicialSyncRun|null $run */
        $run = $this->activeRunsQuery()
            ->where('judicial_sync_runs.data_source', $source)
            ->whereNull('judicial_sync_runs.organization_id')
            ->first();

        return $run;
    }

    /**
     * @return list<JudicialSyncRun>
     */
    private function activeOrganizationRuns(): array
    {
        return $this->activeRunsQuery()
            ->whereNotNull('judicial_sync_runs.organization_id')
            ->get()
            ->all();
    }

    private function activeRunsQuery(): JudicialSyncRunQueryBuilder
    {
        return JudicialSyncRun::query()
            ->with('organization')
            ->withQueueBatchStats()
            ->where('judicial_sync_runs.started_at', '>=', now()->subHours(8))
            ->where(function (Builder $query): void {
                $this->constrainToVisibleRuns($query);
            })
            ->latest('judicial_sync_runs.started_at')
            ->orderByDesc('judicial_sync_runs.id');
    }

    private function constrainToVisibleRuns(Builder $query): void
    {
        $freshStart = now()->subSeconds($this->startedWithoutBatchSeconds());

        $query->where('judicial_sync_runs.status', JudicialSyncRunStatus::BatchPending->value)
            ->orWhere(function (Builder $query) use ($freshStart): void {
                $query->where('judicial_sync_runs.status', JudicialSyncRunStatus::Started->value)
                    ->where('judicial_sync_runs.started_at', '>=', $freshStart);
            });
    }

    private function startedWithoutBatchSeconds(): int
    {
        return max(1, (int) config('judicial-sync.live_status.started_without_batch_seconds', 90));
    }
}

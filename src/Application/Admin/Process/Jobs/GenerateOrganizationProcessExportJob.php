<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Src\Application\Admin\Process\Data\OrganizationProcessExportData;
use Src\Application\Admin\Process\Exports\OrganizationProcessWorkbookExport;
use Src\Application\Admin\Process\Support\OrganizationProcessExportActionQueryService;
use Src\Application\Admin\Process\Support\OrganizationProcessExportQueryService;
use Src\Application\Shared\Services\Notification\NotifyAdminProcessExportFinishedService;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;
use Src\Domain\Process\Enums\ProcessExportStatus;
use Src\Domain\Process\Models\ProcessExport;
use Throwable;

class GenerateOrganizationProcessExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(
        public readonly string $exportId,
        public readonly int $syncDeferCount = 0,
    ) {
        $this->onQueue((string) config('process-export.queue', 'exports'));
        $this->tries = max(1, (int) config('process-export.tries', 5));
        $this->timeout = max(60, (int) config('process-export.timeout', 300));
    }

    public function handle(
        OrganizationProcessExportQueryService $processQueryService,
        OrganizationProcessExportActionQueryService $actionQueryService,
        NotifyAdminProcessExportFinishedService $notifyFinished,
    ): void {
        $export = ProcessExport::query()->find($this->exportId);

        if (! $export instanceof ProcessExport) {
            return;
        }

        if (in_array($export->status, [ProcessExportStatus::Completed, ProcessExportStatus::Failed], true)) {
            return;
        }

        $filters = OrganizationProcessExportData::hydrate($export->filters ?? []);
        if ($filters->include_actions) {
            $this->timeout = max(
                $this->timeout,
                (int) config('process-export.timeout_with_actions', 900)
            );
        }

        if ($this->shouldDeferForActiveSync()) {
            $maxReleases = max(1, (int) config('process-export.sync_defer_max_releases', 60));
            $delay = max(15, (int) config('process-export.sync_defer_seconds', 60));

            if ($this->syncDeferCount < $maxReleases) {
                Log::info('Process export deferred while judicial sync is active', [
                    'export_id' => $export->id,
                    'organization_id' => $export->organization_id,
                    'sync_defer_count' => $this->syncDeferCount,
                    'release_in_seconds' => $delay,
                ]);

                dispatch(new self($this->exportId, $this->syncDeferCount + 1))
                    ->delay(now()->addSeconds($delay));

                return;
            }

            Log::warning('Process export proceeding after sync defer limit', [
                'export_id' => $export->id,
                'organization_id' => $export->organization_id,
                'sync_defer_count' => $this->syncDeferCount,
            ]);
        }

        $lockSeconds = max(60, (int) config('process-export.org_lock_seconds', 600));
        $lock = Cache::lock('process-export:org:'.$export->organization_id, $lockSeconds);

        if (! $lock->get()) {
            dispatch(new self($this->exportId, $this->syncDeferCount))
                ->delay(now()->addSeconds(30));

            return;
        }

        try {
            $this->generate($export, $filters, $processQueryService, $actionQueryService);
            $export->refresh();
            $notifyFinished->handle($export);
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $export = ProcessExport::query()->find($this->exportId);

        if (! $export instanceof ProcessExport) {
            return;
        }

        $export->update([
            'status' => ProcessExportStatus::Failed,
            'error_message' => $exception?->getMessage() ?? 'Export failed',
            'completed_at' => now(),
        ]);

        $export->refresh();

        resolve(NotifyAdminProcessExportFinishedService::class)->handle($export);
    }

    private function shouldDeferForActiveSync(): bool
    {
        if (! (bool) config('process-export.defer_while_sync_active', true)) {
            return false;
        }

        return JudicialSyncRun::hasActiveBatch();
    }

    private function generate(
        ProcessExport $export,
        OrganizationProcessExportData $filters,
        OrganizationProcessExportQueryService $processQueryService,
        OrganizationProcessExportActionQueryService $actionQueryService,
    ): void {
        $export->update([
            'status' => ProcessExportStatus::Processing,
            'started_at' => $export->started_at ?? now(),
            'error_message' => null,
        ]);

        if ($filters->include_actions) {
            $this->assertActionRowLimit($export->organization_id, $filters, $actionQueryService);
        }

        $export->loadMissing('organization');

        $exportedAt = now();
        $disk = (string) config('process-export.disk', 'local');
        $directory = trim((string) config('process-export.directory', 'exports/processes'), '/');
        $fileName = self::buildFileName(
            organizationName: (string) ($export->organization?->name ?: 'organizacion'),
            exportedAt: $exportedAt,
            includeActions: $filters->include_actions,
        );
        $relativePath = $directory.'/'.$export->id.'/'.$fileName;

        $workbook = new OrganizationProcessWorkbookExport(
            organizationId: $export->organization_id,
            filters: $filters,
            processQueryService: $processQueryService,
            actionQueryService: $actionQueryService,
        );

        Excel::store($workbook, $relativePath, $disk);

        if (! Storage::disk($disk)->exists($relativePath)) {
            throw new RuntimeException('Export file was not stored: '.$relativePath);
        }

        $ttlHours = max(1, (int) config('process-export.ttl_hours', 24));

        $export->update([
            'status' => ProcessExportStatus::Completed,
            'row_count' => $workbook->processRowCount(),
            'action_row_count' => $workbook->actionRowCount(),
            'disk' => $disk,
            'file_path' => $relativePath,
            'file_name' => $fileName,
            'completed_at' => $exportedAt,
            'expires_at' => $exportedAt->copy()->addHours($ttlHours),
            'error_message' => null,
        ]);
    }

    private function assertActionRowLimit(
        string $organizationId,
        OrganizationProcessExportData $filters,
        OrganizationProcessExportActionQueryService $actionQueryService,
    ): void {
        $max = max(1, (int) config('process-export.max_action_rows', 100000));
        $count = $actionQueryService->countRows($organizationId, $filters);

        if ($count > $max) {
            throw new RuntimeException(
                "La exportación supera el límite de {$max} actuaciones ({$count}). Reduce el rango de fechas de actuaciones (actions_from / actions_to)."
            );
        }
    }

    /**
     * Human-readable Excel name for history/download.
     * Always uses the generation timestamp so a re-run from history gets today's date.
     */
    public static function buildFileName(
        string $organizationName,
        \DateTimeInterface $exportedAt,
        bool $includeActions = false,
    ): string {
        $orgPart = Str::slug(trim($organizationName));
        if ($orgPart === '') {
            $orgPart = 'organizacion';
        }

        $prefix = $includeActions ? 'procesos-y-actuaciones' : 'procesos';

        return sprintf(
            '%s-%s-%s.xlsx',
            $prefix,
            $orgPart,
            $exportedAt->format('Y-m-d_His'),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Application\Shared\Services\Process\ProcessSyncService;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

/**
 * Removes actuaciones stored on the wrong process instance (cross-instance contamination).
 *
 * Compares each instance's DB rows against the full actuaciones list returned by the
 * Judicial Branch API for that instance's idProceso, then deletes orphans and optionally re-syncs.
 */
class RepairProcessInstanceActionsCommand extends Command
{
    protected $signature = 'judicial:repair-instance-actions
                            {--radicado= : Radicado (process_number) to repair}
                            {--process= : Single process UUID instead of full radicado}
                            {--dry-run : Preview changes without writing}
                            {--sync : Re-sync from API after cleanup}
                            {--notify : When used with --sync, send notifications for newly imported actions}';

    protected $description = 'Remove actuaciones that do not belong to their process instance and optionally re-sync from the Judicial Branch API.';

    public function handle(
        JudicialBranchConsultService $judicialService,
        ProcessSyncService $syncService,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $radicado = $this->option('radicado');
        $processUuid = $this->option('process');

        if (($radicado === null || $radicado === '') && ($processUuid === null || $processUuid === '')) {
            $this->error('Provide --radicado= or --process=.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be written to the database.');
        }

        $processes = $this->resolveProcesses($radicado, $processUuid);

        if ($processes->isEmpty()) {
            $this->warn('No judicial-branch process instances found for the given filter.');

            return self::SUCCESS;
        }

        $logChannel = config('judicial-sync.log_channel', 'judicial_sync_notifications');
        $morphClass = (new ProcessAction)->getMorphClass();
        $totalRemoved = 0;

        foreach ($processes as $process) {
            $apiProcessId = (int) $process->process_id;
            if ($apiProcessId === 0) {
                $this->line("Skipping {$process->id}: missing API process_id.");

                continue;
            }

            $this->info("Inspecting {$process->court} (UUID {$process->id}, API {$apiProcessId})...");

            $judicialService->withSeed($process->process_number);
            $result = $judicialService->fetchActionByProcess($apiProcessId, onlyFirstPage: false);

            if (! $result->isSuccessful) {
                $this->error("  Failed to fetch actuaciones from API for API id {$apiProcessId}.");

                continue;
            }

            $validRegistrationIds = collect($result->data)
                ->map(fn (array $row): int => (int) ($row['idRegActuacion'] ?? 0))
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values();

            $orphans = ProcessAction::query()
                ->where('process_id', $process->id)
                ->whereNotIn('action_registration_id', $validRegistrationIds)
                ->get();

            if ($orphans->isEmpty()) {
                $this->line('  No orphan actuaciones found.');

                continue;
            }

            foreach ($orphans as $action) {
                $this->line(sprintf(
                    '  %s %s | %s | idReg:%d',
                    $dryRun ? '[would remove]' : '[remove]',
                    $action->action_date->format('Y-m-d'),
                    $action->action,
                    $action->action_registration_id,
                ));
            }

            if ($dryRun) {
                $totalRemoved += $orphans->count();

                continue;
            }

            DB::transaction(function () use ($orphans, $process, $morphClass, $logChannel): void {
                foreach ($orphans as $action) {
                    Log::channel($logChannel)->info('RepairProcessInstanceActionsCommand: removed orphan action', [
                        'process_uuid' => $process->id,
                        'api_process_id' => $process->process_id,
                        'action_registration_id' => $action->action_registration_id,
                        'action' => $action->action,
                        'action_date' => $action->action_date->format('Y-m-d'),
                    ]);

                    DB::table('process_action_alert_action_keyword')
                        ->where('process_action_id', $action->id)
                        ->delete();

                    DB::table('process_action_alert_highlights')
                        ->where('process_action_id', $action->id)
                        ->delete();

                    OrganizationNotification::query()
                        ->where('notifiable_type', $morphClass)
                        ->where('notifiable_id', $action->id)
                        ->delete();

                    $action->delete();
                }

                $dbMaxDate = $process->actions()->max('action_date');
                $process->update([
                    'last_activity_date' => $dbMaxDate,
                ]);
            });

            $totalRemoved += $orphans->count();
        }

        $actionLabel = $dryRun ? 'Would remove' : 'Removed';
        $this->newLine();
        $this->info("{$actionLabel} {$totalRemoved} orphan actuacion(es).");

        if ($this->option('sync') && ! $dryRun) {
            $syncRadicado = $this->resolveSyncRadicado($radicado, $processes);

            if ($syncRadicado === null) {
                $this->warn('Could not determine radicado for --sync.');

                return self::SUCCESS;
            }

            $notify = (bool) $this->option('notify');
            $this->info("Re-syncing radicado {$syncRadicado}".($notify ? ' (with notifications)' : ' (without notifications)').'...');

            $syncService->syncByProcessNumber(
                processNumber: $syncRadicado,
                notify: $notify,
                skipInactiveThreshold: true,
            );

            $this->info('Sync completed.');
        } elseif ($this->option('sync') && $dryRun) {
            $this->line('[DRY RUN] Would re-sync after cleanup. Run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Process>
     */
    private function resolveProcesses(?string $radicado, ?string $processUuid): Collection
    {
        $query = Process::query()
            ->where('is_manual_sync', false)
            ->whereNotNull('process_id')
            ->whereHas('processDataSource', fn ($q) => $q->where('slug', ProcessDataSourceSlug::JudicialBranch->value));

        if ($processUuid !== null && $processUuid !== '') {
            $query->where('id', $processUuid);
        } elseif ($radicado !== null && $radicado !== '') {
            $query->where('process_number', $radicado);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Process>  $processes
     */
    private function resolveSyncRadicado(?string $radicado, Collection $processes): ?string
    {
        if ($radicado !== null && $radicado !== '') {
            return $radicado;
        }

        return $processes->first()?->process_number;
    }
}

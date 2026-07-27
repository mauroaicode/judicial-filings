<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Jobs\DispatchOrganizationDigestsJob;
use Src\Application\Shared\Jobs\SyncSamaiProcessJob;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;
use Src\Domain\Process\Models\Process;

/**
 * Cron diario para sincronizar procesos de SAMAI (Consejo de Estado).
 *
 * Complementa a SyncJudicialProcessesCommand, que maneja la Rama Judicial.
 * Ambos comandos pueden correr en paralelo o en horarios distintos sin
 * interferencia, ya que cada uno actúa sobre radicados de su propia fuente
 * y escribe su propia fila en judicial_sync_runs.
 *
 * Uso:
 *   php artisan samai:sync-processes
 *   php artisan samai:sync-processes --radicado=11001032400020230012300
 */
class SyncSamaiProcessesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'samai:sync-processes
                            {--radicado= : Sincronizar únicamente este radicado (process_number)}';

    /**
     * @var string
     */
    protected $description = 'Sincroniza procesos del Consejo de Estado (SAMAI) con las últimas actuaciones';

    public function handle(): int
    {
        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');
        $radicadoOption = $this->option('radicado');
        $radicadoFilter = ($radicadoOption !== null && $radicadoOption !== '') ? $radicadoOption : null;

        $run = JudicialSyncRun::startRun($radicadoFilter, JudicialSyncDataSource::Samai);

        Log::channel($channel)->info('SyncSamaiProcessesCommand started', [
            'run_id' => $run->id,
            'data_source' => JudicialSyncDataSource::Samai->value,
            'radicado_filter' => $radicadoFilter,
        ]);

        $processNumbers = Process::query()
            ->forSamaiDailySync($radicadoFilter)
            ->pluck('processes.process_number');

        $total = $processNumbers->count();

        if ($total === 0) {
            $run->markNoProcesses();
            $this->info('No radicados SAMAI para sincronizar.');
            Log::channel($channel)->info('SyncSamaiProcessesCommand: no radicados to sync.', [
                'run_id' => $run->id,
            ]);

            return self::SUCCESS;
        }

        $this->info("Encontrados {$total} radicados SAMAI para sincronizar.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $jobs = [];
        foreach ($processNumbers as $processNumber) {
            $jobs[] = new SyncSamaiProcessJob((string) $processNumber);
            $bar->advance();
        }

        $runId = $run->id;
        $queue = config('judicial-sync.jobs.sync_samai_process.queue', 'samai-sync');

        try {
            $laravelBatch = Bus::batch($jobs)
                ->name('Sync SAMAI Processes Batch')
                ->finally(function (Batch $batch) use ($runId, $channel): void {
                    // Skipped when JUDICIAL_SYNC_AUTO_DIGEST=false so the admin can send
                    // one consolidated package after all sources (Rama + SAMAI + Excel) finish.
                    if (config('judicial-sync.auto_digest_after_sync', true)) {
                        DispatchOrganizationDigestsJob::dispatchSync();
                    }

                    Log::channel($channel)->info('SyncSamaiProcessesCommand: digest dispatch completed after batch', [
                        'run_id' => $runId,
                        'batch_id' => $batch->id,
                        'auto_digest' => config('judicial-sync.auto_digest_after_sync', true),
                    ]);

                    $record = JudicialSyncRun::query()->find($runId);
                    if ($record !== null) {
                        $record->completeBatch($batch);
                    }
                })
                ->onQueue($queue)
                ->dispatch();

            $bar->finish();
            $this->newLine();

            $run->markBatchQueued($laravelBatch->id, count($jobs), $laravelBatch);

            Log::channel($channel)->info('SyncSamaiProcessesCommand: batch dispatched', [
                'run_id' => $run->id,
                'jobs_count' => count($jobs),
                'laravel_batch_id' => $laravelBatch->id,
                'queue' => $queue,
            ]);

            $this->info('Se despacharon '.count($jobs).' jobs SAMAI en un batch.');
            $this->info('Las notificaciones se consolidarán al terminar el batch.');

        } catch (\Throwable $e) {
            $run->markDispatchFailed($e->getMessage());
            $this->error('Error al despachar el batch SAMAI: '.$e->getMessage());
            Log::channel($channel)->error('SyncSamaiProcessesCommand: batch dispatch failed', [
                'run_id' => $run->id,
                'message' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

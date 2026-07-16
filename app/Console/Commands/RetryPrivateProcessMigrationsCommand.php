<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Jobs\MigratePrivateProcessSourceJob;
use Src\Domain\Process\Models\Process;

/**
 * Reintenta la migración de procesos que quedaron atrapados como privados en Rama Judicial
 * sin haber sido migrados a SAMAI por el primer intento de MigratePrivateProcessSourceJob.
 *
 * ¿Por qué puede fallar el primer intento?
 *  - El Consejo de Estado tarda días en publicar el proceso en SAMAI.
 *  - La API de SAMAI estaba caída o lenta en el momento de la migración.
 *  - El proceso aún no ha sido cargado en el sistema SAMAI de su corporación.
 *
 * Patrón de backoff por niveles (configurable vía config/judicial-sync.php):
 *  Nivel 1: became_private_at entre 1-3 días → primer reintento
 *  Nivel 2: became_private_at entre 3-7 días → segundo reintento
 *  Nivel 3: became_private_at > 7 días      → reintento final, luego se rinde
 *
 * Este comando debe correr UNA VEZ AL DÍA (no cada hora) para no saturar SAMAI.
 *
 * Cómo lo hacen los grandes sistemas:
 *  - No hacen polling constante: usan backoff exponencial por niveles.
 *  - Registran el historial de intentos en DB para no reintentar infinitamente.
 *  - Alertan al operador cuando un proceso supera el umbral máximo de días sin migrar.
 */
class RetryPrivateProcessMigrationsCommand extends Command
{
    protected $signature = 'judicial:retry-private-migrations
                            {--dry-run : Solo muestra cuántos procesos se reintentarían, sin disparar jobs}';

    protected $description = 'Reintenta migrar a SAMAI los procesos que quedaron privados en Rama Judicial';

    public function handle(): int
    {
        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');
        $isDryRun = (bool) $this->option('dry-run');

        $level1Days = (int) config('judicial-sync.private_migration_retry_level1_days', 1);
        $level2Days = (int) config('judicial-sync.private_migration_retry_level2_days', 3);
        $level3Days = (int) config('judicial-sync.private_migration_retry_level3_days', 7);
        $giveUpDays = (int) config('judicial-sync.private_migration_give_up_days', 14);

        // Obtener radicados atrapados: privados en JB, sin migrar a SAMAI.
        $stuck = Process::query()
            ->forPendingPrivateMigration()
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No hay procesos privados pendientes de migración.');

            return self::SUCCESS;
        }

        $dispatched = 0;
        $skipped = 0;
        $warned = 0;
        $now = now();

        foreach ($stuck as $row) {
            $processNumber = (string) $row->process_number;
            $becamePrivateAt = $row->became_private_at;

            if (! $becamePrivateAt) {
                continue;
            }

            $daysSince = (int) $becamePrivateAt->diffInDays($now);

            // Nivel de reintento basado en días transcurridos.
            $shouldRetry = match (true) {
                $daysSince >= $level1Days && $daysSince < $level2Days => 'level1',
                $daysSince >= $level2Days && $daysSince < $level3Days => 'level2',
                $daysSince >= $level3Days && $daysSince < $giveUpDays => 'level3',
                default => null,
            };

            if ($daysSince >= $giveUpDays) {
                // El proceso lleva demasiado tiempo privado sin migrar → alertar al operador.
                Log::channel($channel)->warning('RetryPrivateMigrations: proceso privado sin migrar supera el umbral máximo', [
                    'process_number' => $processNumber,
                    'became_private_at' => $becamePrivateAt->toDateString(),
                    'days_since' => $daysSince,
                    'give_up_days' => $giveUpDays,
                ]);
                $this->warn("⚠ {$processNumber} privado hace {$daysSince} días, supera límite de {$giveUpDays} días.");
                $warned++;

                continue;
            }

            if ($shouldRetry === null) {
                $skipped++;

                continue;
            }

            if ($isDryRun) {
                $this->line("[dry-run] [{$shouldRetry}] {$processNumber} (privado hace {$daysSince} días)");
                $dispatched++;

                continue;
            }

            // Escalonar jobs con delay para no saturar la API de SAMAI.
            $delaySeconds = $dispatched * 5;

            MigratePrivateProcessSourceJob::dispatch($processNumber)
                ->delay(now()->addSeconds($delaySeconds));

            Log::channel($channel)->info('RetryPrivateMigrations: job dispatched', [
                'process_number' => $processNumber,
                'retry_level' => $shouldRetry,
                'days_since' => $daysSince,
                'delay_seconds' => $delaySeconds,
            ]);

            $dispatched++;
        }

        $this->info("Dispatched: {$dispatched} | Skipped: {$skipped} | Warnings: {$warned}");

        Log::channel($channel)->info('RetryPrivateProcessMigrationsCommand finished', [
            'dispatched' => $dispatched,
            'skipped' => $skipped,
            'warned' => $warned,
            'dry_run' => $isDryRun,
        ]);

        return self::SUCCESS;
    }
}

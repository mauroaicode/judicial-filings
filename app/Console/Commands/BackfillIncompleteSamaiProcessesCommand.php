<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Application\Shared\Services\Process\BackfillIncompleteSamaiProcessesService;

/**
 * Repara procesos SAMAI que quedaron incompletos tras un import:
 *  - sin Despacho (court) y/o Clase de proceso
 *  - solo con la última página de actuaciones del portal HTML
 *
 * Uso:
 *   php artisan samai:backfill-incomplete --radicado=76001333301320160005700
 *   php artisan samai:backfill-incomplete --dry-run
 *   php artisan samai:backfill-incomplete --organization=UUID
 *   php artisan samai:backfill-incomplete --all
 */
class BackfillIncompleteSamaiProcessesCommand extends Command
{
    protected $signature = 'samai:backfill-incomplete
                            {--radicado= : Reparar únicamente este process_number}
                            {--organization= : Limitar a procesos de esta organización}
                            {--all : Incluir todos los SAMAI con corporación (no solo incompletos)}
                            {--notify : Notificar actuaciones nuevas insertadas}
                            {--dry-run : Solo listar candidatos sin modificar}';

    protected $description = 'Completa despacho/clase y actuaciones faltantes en procesos SAMAI incompletos';

    public function handle(BackfillIncompleteSamaiProcessesService $service): int
    {
        $radicadoOption = $this->option('radicado');
        $organizationOption = $this->option('organization');
        $radicado = is_string($radicadoOption) && $radicadoOption !== '' ? $radicadoOption : null;
        $organizationId = is_string($organizationOption) && $organizationOption !== ''
            ? $organizationOption
            : null;
        $onlyIncomplete = ! (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');
        $notify = (bool) $this->option('notify');

        // Con --radicado siempre se intenta reparar ese proceso aunque ya tenga metadata.
        if ($radicado !== null) {
            $onlyIncomplete = false;
        }

        $candidates = $service->queryCandidates($radicado, $organizationId, $onlyIncomplete);

        if ($candidates->isEmpty()) {
            $this->info('No hay procesos SAMAI candidatos para backfill.');

            return self::SUCCESS;
        }

        $this->info('Candidatos: '.$candidates->count());
        $this->table(
            ['Radicado', 'Corporación', 'Court', 'Clase', 'Actuaciones'],
            $candidates->map(static function ($process): array {
                return [
                    $process->process_number,
                    $process->samai_corporacion,
                    trim((string) $process->court) !== ''
                        ? mb_substr((string) $process->court, 0, 40)
                        : '(vacío)',
                    trim((string) $process->process_class) !== ''
                        ? mb_substr((string) $process->process_class, 0, 30)
                        : '(vacío)',
                    (string) $process->actions()->count(),
                ];
            })->all()
        );

        if ($dryRun) {
            $this->warn('[DRY RUN] No se modificó nada.');

            return self::SUCCESS;
        }

        if ($notify) {
            $this->warn('Se notificarán actuaciones nuevas (--notify).');
        } else {
            $this->line('Actuaciones nuevas sin notificación (usa --notify para alertar).');
        }

        $summary = $service->handle(
            radicado: $radicado,
            organizationId: $organizationId,
            onlyIncomplete: $onlyIncomplete,
            dryRun: false,
            notify: $notify,
        );

        $this->newLine();
        $this->info("Reparados: {$summary['repaired']}");
        $this->info("Metadata actualizada: {$summary['metadata_updated']}");
        $this->info("Actuaciones agregadas: {$summary['actions_added']}");
        $this->info("Sujetos agregados: {$summary['subjects_added']}");
        $this->info("Fallidos: {$summary['failed']}");

        if ($summary['failures'] !== []) {
            $this->table(
                ['Radicado', 'Process ID', 'Error'],
                array_map(
                    static fn (array $row): array => [
                        $row['process_number'],
                        $row['process_id'],
                        mb_substr($row['error'], 0, 80),
                    ],
                    $summary['failures']
                )
            );
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Application\Shared\Services\Process\RepairPhantomProcessInstancesService;
use Src\Domain\Process\Models\Process;

class RepairPhantomProcessInstancesCommand extends Command
{
    protected $signature = 'judicial:repair-phantom-instances
                            {--radicado= : Radicado (process_number) to repair}
                            {--process= : Single process UUID (repairs the full radicado)}
                            {--all : Repair every affected radicado}
                            {--force : Skip confirmation when using --all without --dry-run}
                            {--dry-run : Preview changes without writing}';

    protected $description = 'Remove duplicate actuaciones and notifications caused by phantom Rama Judicial folders for the same radicado.';

    public function handle(RepairPhantomProcessInstancesService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $radicado = $this->option('radicado');
        $processUuid = $this->option('process');
        $all = (bool) $this->option('all');

        $filters = [
            ($radicado !== null && $radicado !== ''),
            ($processUuid !== null && $processUuid !== ''),
            $all,
        ];

        if (count(array_filter($filters)) !== 1) {
            $this->error('Provide exactly one of --radicado=, --process=, or --all.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be written to the database.');
        }

        $radicados = $this->resolveRadicados($radicado, $processUuid, $all, $service);

        if ($radicados === []) {
            $this->warn('No affected radicados found for the given filter.');

            return self::SUCCESS;
        }

        if ($all && ! $dryRun && ! (bool) $this->option('force')) {
            $count = count($radicados);
            if (! $this->confirm("Repair {$count} radicado(s) with phantom/duplicate actuaciones?", false)) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        if ($all) {
            $this->info('Scanning '.count($radicados).' radicado(s)...');
        }

        $totalActions = 0;
        $totalNotifications = 0;
        $totalPhantoms = 0;
        $affectedRadicados = 0;

        foreach ($radicados as $processNumber) {
            $result = $service->repairRadicado($processNumber, $dryRun);

            if ($result->actionsRemoved === 0 && $result->notificationsRemoved === 0 && $result->phantomInstancesDetected === 0) {
                continue;
            }

            $affectedRadicados++;
            $totalActions += $result->actionsRemoved;
            $totalNotifications += $result->notificationsRemoved;
            $totalPhantoms += $result->phantomInstancesDetected;

            $prefix = $dryRun ? '[would repair]' : '[repaired]';
            $this->line(sprintf(
                '%s %s — phantom instance(s): %d, actuaciones: %d, notifications: %d',
                $prefix,
                $processNumber,
                $result->phantomInstancesDetected,
                $result->actionsRemoved,
                $result->notificationsRemoved,
            ));
        }

        $this->newLine();
        $actionLabel = $dryRun ? 'Would remove' : 'Removed';
        $this->info("Affected radicados: {$affectedRadicados}");
        $this->info("Phantom instances detected (metadata): {$totalPhantoms}");
        $this->info("{$actionLabel} duplicate actuacion(es): {$totalActions}");
        $this->info("{$actionLabel} duplicate notification(s): {$totalNotifications}");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveRadicados(
        ?string $radicado,
        ?string $processUuid,
        bool $all,
        RepairPhantomProcessInstancesService $service,
    ): array {
        if ($all) {
            return $service->findAffectedRadicados();
        }

        if ($processUuid !== null && $processUuid !== '') {
            $processNumber = Process::query()->where('id', $processUuid)->value('process_number');

            return is_string($processNumber) && $processNumber !== '' ? [$processNumber] : [];
        }

        return is_string($radicado) && $radicado !== '' ? [$radicado] : [];
    }
}

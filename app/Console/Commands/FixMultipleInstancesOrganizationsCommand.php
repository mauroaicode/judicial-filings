<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process;
use Illuminate\Support\Facades\Log;

class FixMultipleInstancesOrganizationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'process:fix-multiple-instances-organizations 
                            {--process-number= : Número de radicado específico a corregir}
                            {--dry-run : Solo mostrar qué se haría sin ejecutar cambios}';

    /**
     * The console command description.
     */
    protected $description = 'Corrige la asignación de organizaciones en procesos con múltiples instancias';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $processNumber = $this->option('process-number');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 MODO DRY-RUN: Solo se mostrará qué se haría sin ejecutar cambios');
        }

        if ($processNumber) {
            $this->fixSpecificProcess($processNumber, $dryRun);
        } else {
            $this->fixAllMultipleInstances($dryRun);
        }

        return Command::SUCCESS;
    }

    /**
     * Corrige un proceso específico
     */
    private function fixSpecificProcess(string $processNumber, bool $dryRun): void
    {
        $this->info("🔧 Corrigiendo proceso específico: {$processNumber}");

        $processes = Process::where('process_number', $processNumber)->get();

        if ($processes->isEmpty()) {
            $this->error("❌ No se encontraron procesos para el radicado: {$processNumber}");
            return;
        }

        if ($processes->count() == 1) {
            $this->info("ℹ️ El radicado {$processNumber} solo tiene una instancia. No requiere corrección.");
            return;
        }

        $this->fixProcessInstances($processes, $dryRun);
    }

    /**
     * Corrige todos los procesos con múltiples instancias
     */
    private function fixAllMultipleInstances(bool $dryRun): void
    {
        $this->info('🔍 Buscando procesos con múltiples instancias...');

        // Buscar números de radicado que tengan múltiples instancias
        $processNumbers = Process::select('process_number')
            ->groupBy('process_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('process_number');

        if ($processNumbers->isEmpty()) {
            $this->info('✅ No se encontraron procesos con múltiples instancias.');
            return;
        }

        $this->info("📊 Encontrados {$processNumbers->count()} radicados con múltiples instancias");

        $totalFixed = 0;
        $totalProcesses = 0;

        foreach ($processNumbers as $processNumber) {
            $processes = Process::where('process_number', $processNumber)->get();
            $fixed = $this->fixProcessInstances($processes, $dryRun);
            
            if ($fixed > 0) {
                $totalFixed += $fixed;
            }
            $totalProcesses += $processes->count();
        }

        $this->info("📈 Resumen:");
        $this->info("   - Total de procesos procesados: {$totalProcesses}");
        $this->info("   - Procesos corregidos: {$totalFixed}");
    }

    /**
     * Corrige las instancias de un proceso específico
     */
    private function fixProcessInstances($processes, bool $dryRun): int
    {
        $processNumber = $processes->first()->process_number;
        $fixed = 0;

        $this->info("🔧 Procesando radicado: {$processNumber} ({$processes->count()} instancias)");

        // Obtener organizaciones del primer proceso (que debería tener todas las organizaciones)
        $firstProcess = $processes->first();
        $organizations = $firstProcess->organizations()->where('organization_processes.is_active', true)->get();

        if ($organizations->isEmpty()) {
            $this->warn("⚠️ El primer proceso del radicado {$processNumber} no tiene organizaciones asignadas");
            return 0;
        }

        $organizationIds = $organizations->pluck('id')->toArray();
        $this->info("   📋 Organizaciones a asignar: {$organizations->count()}");

        foreach ($processes as $process) {
            $existingOrgs = $process->organizations()->pluck('organizations.id')->toArray();
            
            $this->info("   🔍 Process ID {$process->process_id}: {$process->organizations()->count()} organizaciones");

            if (count($existingOrgs) == 0) {
                if ($dryRun) {
                    $this->info("   🔄 [DRY-RUN] Se asignarían " . count($organizationIds) . " organizaciones");
                } else {
                    $pivotData = [];
                    foreach ($organizationIds as $orgId) {
                        $pivotData[$orgId] = [
                            'is_active' => true,
                            'interest_date' => now(),
                        ];
                    }

                    $process->organizations()->attach($pivotData);
                    $this->info("   ✅ Asignadas " . count($organizationIds) . " organizaciones");
                    
                    Log::channel('judicial_process_chunk_job')->info("Organizaciones asignadas a proceso con múltiples instancias", [
                        'process_id' => $process->process_id,
                        'process_number' => $processNumber,
                        'organizations_count' => count($organizationIds),
                        'organizations_ids' => $organizationIds
                    ]);
                }
                $fixed++;
            } else {
                $this->info("   ℹ️ Ya tiene organizaciones asignadas");
            }
        }

        return $fixed;
    }
}

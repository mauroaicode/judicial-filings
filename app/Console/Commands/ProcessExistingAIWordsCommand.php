<?php

namespace App\Console\Commands;

use Core\Shared\Infrastructure\Persistence\Eloquent\Models\ProcessAction;
use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotification;
use Core\Shared\Domain\Enums\NotificationType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessExistingAIWordsCommand extends Command
{
    protected $signature = 'notifications:process-ai-words {--dry-run}';
    protected $description = 'Process existing actions with AI words to create missing ai_words_process_action notifications';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info("🔍 MODO DRY-RUN - No se crearán registros reales");
        }

        // Función para normalizar texto
        $normalizeText = function($text) {
            $text = str_replace(['ó', 'Ó', 'ó', 'Ó'], 'O', $text);
            $text = str_replace(['á', 'Á', 'á', 'Á'], 'A', $text);
            $text = str_replace(['é', 'É', 'é', 'É'], 'E', $text);
            $text = str_replace(['í', 'Í', 'í', 'Í'], 'I', $text);
            $text = str_replace(['ú', 'Ú', 'ú', 'Ú'], 'U', $text);
            $text = str_replace(['ñ', 'Ñ', 'ñ', 'Ñ'], 'N', $text);
            return $text;
        };

        // Función para verificar palabras clave
        $containsAIWords = function($action, $annotation) use ($normalizeText) {
            $actionText = $normalizeText(strtoupper(trim($action ?? '')));
            $annotationText = $normalizeText(strtoupper(trim($annotation ?? '')));
            
            $aiWords = ['CONSULTA', 'APELACION'];
            
            foreach ($aiWords as $word) {
                $normalizedWord = $normalizeText($word);
                if (str_contains($actionText, $normalizedWord) || str_contains($annotationText, $normalizedWord)) {
                    return true;
                }
            }
            return false;
        };

        // Obtener actuaciones con palabras clave
        $actions = ProcessAction::where('action', 'like', '%CONSULTA%')
            ->orWhere('action', 'like', '%APELACIÓN%')
            ->orWhere('action', 'like', '%APELACION%')
            ->orWhere('annotation', 'like', '%CONSULTA%')
            ->orWhere('annotation', 'like', '%APELACIÓN%')
            ->orWhere('annotation', 'like', '%APELACION%')
            ->get();

        $this->info("📊 Total actuaciones encontradas: " . $actions->count());

        $processedCount = 0;
        $createdCount = 0;
        $skippedCount = 0;

        foreach ($actions as $action) {
            $processedCount++;
            
            // Verificar si realmente contiene palabras clave con el método corregido
            if (!$containsAIWords($action->action, $action->annotation)) {
                $skippedCount++;
                continue;
            }

            // Verificar si ya tiene notificación ai_words_process_action
            $existingNotification = OrganizationNotification::where('notifiable_id', $action->id)
                ->where('notifiable_type', 'Core\\Shared\\Infrastructure\\Persistence\\Eloquent\\Models\\ProcessAction')
                ->where('notification_type', NotificationType::AI_WORDS_PROCESS_ACTION->value)
                ->first();

            if ($existingNotification) {
                $skippedCount++;
                $this->line("⏭️  Saltando acción {$action->id} - ya tiene notificación AI");
                continue;
            }

            // Obtener organizaciones interesadas en el proceso
            $process = $action->process;
            if (!$process) {
                $this->error("❌ Proceso no encontrado para acción {$action->id}");
                continue;
            }

            $interestedOrganizations = $process->organizations()
                ->where('organization_processes.is_active', true)
                ->get();

            if ($interestedOrganizations->isEmpty()) {
                $this->line("⚠️  No hay organizaciones interesadas para acción {$action->id}");
                continue;
            }

            $this->line("✅ Procesando acción {$action->id} - {$action->action}");

            if (!$dryRun) {
                // Crear notificaciones para cada organización
                foreach ($interestedOrganizations as $org) {
                    OrganizationNotification::updateOrCreate(
                        [
                            'organization_id' => $org->id,
                            'notifiable_id' => $action->id,
                            'notifiable_type' => 'Core\\Shared\\Infrastructure\\Persistence\\Eloquent\\Models\\ProcessAction',
                            'notification_type' => NotificationType::AI_WORDS_PROCESS_ACTION->value,
                        ],
                        [
                            'is_viewed' => false,
                            'is_notified' => false,
                            'viewed_at' => null,
                            'notified_at' => null,
                        ]
                    );
                }
            }

            $createdCount++;
            $this->line("   📝 Creadas notificaciones para " . $interestedOrganizations->count() . " organizaciones");
        }

        $this->info("");
        $this->info("📊 RESUMEN:");
        $this->info("  Total procesadas: {$processedCount}");
        $this->info("  Creadas: {$createdCount}");
        $this->info("  Saltadas: {$skippedCount}");

        if ($dryRun) {
            $this->info("🔍 MODO DRY-RUN - Ejecuta sin --dry-run para crear los registros reales");
        } else {
            $this->info("✅ Proceso completado");
        }

        return 0;
    }
}

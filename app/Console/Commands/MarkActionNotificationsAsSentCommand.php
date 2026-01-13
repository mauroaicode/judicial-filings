<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotification;
use Illuminate\Support\Facades\Log;

class MarkActionNotificationsAsSentCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notifications:mark-action-notifications-sent 
                            {--dry-run : Solo mostrar qué se haría sin ejecutar cambios}
                            {--limit=100 : Límite de notificaciones a procesar}';

    /**
     * The console command description.
     */
    protected $description = 'Marca las notificaciones de actuaciones como enviadas (is_notified = true)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        if ($dryRun) {
            $this->info('🔍 MODO DRY-RUN: Solo se mostrará qué se haría sin ejecutar cambios');
        }

        $this->info("🔧 Marcando notificaciones de actuaciones como enviadas...");

        // Buscar notificaciones de actuaciones que no están marcadas como enviadas
        $notifications = OrganizationNotification::whereIn('notification_type', ['new_process_action', 'ai_words_process_action'])
            ->where('is_notified', false)
            ->limit($limit)
            ->get();

        if ($notifications->isEmpty()) {
            $this->info('✅ No se encontraron notificaciones de actuaciones pendientes de marcar como enviadas.');
            return Command::SUCCESS;
        }

        $this->info("📊 Encontradas {$notifications->count()} notificaciones de actuaciones pendientes");

        $updated = 0;
        $errors = 0;

        foreach ($notifications as $notification) {
            try {
                if ($dryRun) {
                    $this->info("   🔄 [DRY-RUN] Se marcaría como enviada: {$notification->notification_type} - Org: {$notification->organization_id} - Notifiable: {$notification->notifiable_id}");
                } else {
                    $notification->update([
                        'is_notified' => true,
                        'notified_at' => now()
                    ]);
                    
                    $this->info("   ✅ Marcada como enviada: {$notification->notification_type} - Org: {$notification->organization_id} - Notifiable: {$notification->notifiable_id}");
                    
                    Log::channel('notifications')->info('Notificación de actuación marcada como enviada manualmente', [
                        'notification_id' => $notification->id,
                        'organization_id' => $notification->organization_id,
                        'notifiable_id' => $notification->notifiable_id,
                        'notifiable_type' => $notification->notifiable_type,
                        'notification_type' => $notification->notification_type,
                        'marked_at' => now()
                    ]);
                }
                $updated++;
            } catch (\Exception $e) {
                $this->error("   ❌ Error marcando notificación {$notification->id}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->info("📈 Resumen:");
        $this->info("   - Notificaciones procesadas: {$notifications->count()}");
        $this->info("   - Marcadas como enviadas: {$updated}");
        if ($errors > 0) {
            $this->error("   - Errores: {$errors}");
        }

        if (!$dryRun && $updated > 0) {
            $this->info("✅ Comando completado exitosamente");
        } elseif ($dryRun) {
            $this->info("🔍 Modo dry-run completado. Ejecuta sin --dry-run para aplicar los cambios.");
        }

        return Command::SUCCESS;
    }
}

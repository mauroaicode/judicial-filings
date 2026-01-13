<?php

namespace App\Console\Commands;

use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotification;
use Illuminate\Console\Command;

class MonitorNotificationsCommand extends Command
{
    protected $signature = 'notifications:monitor {--interval=10}';
    protected $description = 'Monitor notification processing progress';

    public function handle()
    {
        $interval = (int) $this->option('interval');
        
        $this->info("🔍 Monitoreando notificaciones cada {$interval} segundos...");
        $this->info("Presiona Ctrl+C para detener");
        
        $previousSent = 0;
        $startTime = now();
        
        while (true) {
            $total = OrganizationNotification::count();
            $sent = OrganizationNotification::where('is_notified', true)->count();
            $pending = OrganizationNotification::where('is_notified', false)->count();
            
            $newSent = $sent - $previousSent;
            $elapsed = $startTime->diffInSeconds(now());
            $rate = $elapsed > 0 ? round($sent / $elapsed * 60, 2) : 0; // por minuto
            
            $this->line("📊 [{$startTime->format('H:i:s')}] Total: {$total} | Enviadas: {$sent} | Pendientes: {$pending} | Nuevas: {$newSent} | Tasa: {$rate}/min");
            
            if ($pending === 0) {
                $this->info("✅ ¡Todas las notificaciones han sido procesadas!");
                break;
            }
            
            $previousSent = $sent;
            sleep($interval);
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Command to process notification queues with specific configurations
 */
class ProcessNotificationQueuesCommand extends Command
{
    protected $signature = 'notifications:process-queues 
                            {--email-workers=2 : Number of email workers}
                            {--whatsapp-workers=1 : Number of WhatsApp workers}
                            {--sms-workers=1 : Number of SMS workers}
                            {--internal-workers=2 : Number of internal workers}
                            {--timeout=300 : Timeout in seconds}
                            {--tries=3 : Number of tries}';

    protected $description = 'Process notification queues with optimized settings for each channel type';

    public function handle(): int
    {
        $this->info('🚀 Iniciando procesamiento de colas de notificaciones...');
        
        $emailWorkers = $this->option('email-workers');
        $whatsappWorkers = $this->option('whatsapp-workers');
        $smsWorkers = $this->option('sms-workers');
        $internalWorkers = $this->option('internal-workers');
        $timeout = $this->option('timeout');
        $tries = $this->option('tries');

        $this->info("📧 Email workers: {$emailWorkers}");
        $this->info("📱 WhatsApp workers: {$whatsappWorkers}");
        $this->info("📲 SMS workers: {$smsWorkers}");
        $this->info("🏠 Internal workers: {$internalWorkers}");

        // Start email queue workers
        if ($emailWorkers > 0) {
            $this->startQueueWorker('notifications-email', $emailWorkers, $timeout, $tries);
        }

        // Start WhatsApp queue workers
        if ($whatsappWorkers > 0) {
            $this->startQueueWorker('notifications-whatsapp', $whatsappWorkers, $timeout, $tries);
        }

        // Start SMS queue workers
        if ($smsWorkers > 0) {
            $this->startQueueWorker('notifications-sms', $smsWorkers, $timeout, $tries);
        }

        // Start internal queue workers
        if ($internalWorkers > 0) {
            $this->startQueueWorker('notifications-internal', $internalWorkers, $timeout, $tries);
        }

        $this->info('✅ Todos los workers de notificaciones han sido iniciados');
        
        return Command::SUCCESS;
    }

    private function startQueueWorker(string $queue, int $workers, int $timeout, int $tries): void
    {
        $this->info("🔄 Iniciando {$workers} worker(s) para cola: {$queue}");
        
        for ($i = 1; $i <= $workers; $i++) {
            $command = "php artisan queue:work --queue={$queue} --timeout={$timeout} --tries={$tries} --delay=30";
            
            // Start worker in background
            Process::start($command);
            
            $this->line("  ✓ Worker {$i} iniciado para {$queue}");
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Command to monitor specific notification queues
 */
class MonitorNotificationQueuesCommand extends Command
{
    protected $signature = 'notifications:monitor-queues 
                            {--interval=5 : Monitoring interval in seconds}
                            {--channels=* : Specific channels to monitor (email,whatsapp,sms,internal)}';

    protected $description = 'Monitor notification queues with detailed statistics per channel';

    public function handle(): int
    {
        $interval = $this->option('interval');
        $channels = $this->option('channels');
        
        // Default to all channels if none specified
        if (empty($channels)) {
            $channels = ['email', 'whatsapp', 'sms', 'internal'];
        }

        $this->info("🔍 Monitoreando colas de notificaciones cada {$interval} segundos...");
        $this->info("📊 Canales: " . implode(', ', $channels));
        $this->line("Presiona Ctrl+C para detener");
        $this->newLine();

        while (true) {
            $this->displayQueueStats($channels);
            sleep($interval);
        }

        return Command::SUCCESS;
    }

    private function displayQueueStats(array $channels): void
    {
        $timestamp = now()->format('H:i:s');
        
        foreach ($channels as $channel) {
            $queueName = "notifications-{$channel}";
            $stats = $this->getQueueStats($queueName);
            
            $this->line("📊 [{$timestamp}] {$channel}: Total={$stats['total']} | Pending={$stats['pending']} | Failed={$stats['failed']} | Rate={$stats['rate']}/min");
        }
        
        $this->newLine();
    }

    private function getQueueStats(string $queueName): array
    {
        // Get pending jobs
        $pending = DB::table('jobs')
            ->where('queue', $queueName)
            ->count();

        // Get failed jobs
        $failed = DB::table('failed_jobs')
            ->where('queue', $queueName)
            ->count();

        // Get total notifications for this channel type
        $channelType = str_replace('notifications-', '', $queueName);
        $total = DB::table('organization_notifications')
            ->where('notification_type', 'like', "%{$channelType}%")
            ->count();

        // Calculate rate (simplified)
        $rate = $pending > 0 ? round(60 / max($pending, 1), 2) : 0;

        return [
            'total' => $total,
            'pending' => $pending,
            'failed' => $failed,
            'rate' => $rate
        ];
    }
}

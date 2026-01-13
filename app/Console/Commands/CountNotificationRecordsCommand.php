<?php

namespace App\Console\Commands;

use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotification;
use Illuminate\Console\Command;

class CountNotificationRecordsCommand extends Command
{
    protected $signature = 'notifications:count {--type=} {--status=} {--date=}';
    protected $description = 'Count notification records by type and status';

    public function handle()
    {
        $type = $this->option('type');
        $status = $this->option('status');
        $date = $this->option('date');

        $query = OrganizationNotification::query();

        // Filtrar por tipo si se especifica
        if ($type) {
            $query->where('notification_type', $type);
        }

        // Filtrar por estado si se especifica
        if ($status === 'sent') {
            $query->where('is_notified', true);
        } elseif ($status === 'pending') {
            $query->where('is_notified', false);
        }

        // Filtrar por fecha si se especifica
        if ($date) {
            $query->whereDate('created_at', $date);
        }

        $total = $query->count();

        // Contar por tipo
        $byType = OrganizationNotification::query()
            ->selectRaw('notification_type, COUNT(*) as count')
            ->groupBy('notification_type')
            ->get()
            ->pluck('count', 'notification_type')
            ->toArray();

        // Contar por estado
        $byStatus = OrganizationNotification::query()
            ->selectRaw('is_notified, COUNT(*) as count')
            ->groupBy('is_notified')
            ->get()
            ->pluck('count', 'is_notified')
            ->toArray();

        // Contar por tipo y estado
        $byTypeAndStatus = OrganizationNotification::query()
            ->selectRaw('notification_type, is_notified, COUNT(*) as count')
            ->groupBy('notification_type', 'is_notified')
            ->get()
            ->groupBy('notification_type')
            ->map(function ($group) {
                return $group->pluck('count', 'is_notified')->toArray();
            })
            ->toArray();

        $this->info("📊 RESUMEN DE NOTIFICACIONES");
        $this->info("================================");
        
        if ($type || $status || $date) {
            $this->info("Filtros aplicados:");
            if ($type) $this->info("  - Tipo: {$type}");
            if ($status) $this->info("  - Estado: {$status}");
            if ($date) $this->info("  - Fecha: {$date}");
            $this->info("Total con filtros: {$total}");
            $this->info("");
        }

        $this->info("📈 POR TIPO:");
        foreach ($byType as $notificationType => $count) {
            $this->info("  {$notificationType}: {$count}");
        }
        $this->info("");

        $this->info("📊 POR ESTADO:");
        $this->info("  Enviadas (is_notified = 1): " . ($byStatus[1] ?? 0));
        $this->info("  Pendientes (is_notified = 0): " . ($byStatus[0] ?? 0));
        $this->info("");

        $this->info("🔍 DETALLE POR TIPO Y ESTADO:");
        foreach ($byTypeAndStatus as $notificationType => $statuses) {
            $this->info("  {$notificationType}:");
            $this->info("    Enviadas: " . ($statuses[1] ?? 0));
            $this->info("    Pendientes: " . ($statuses[0] ?? 0));
        }
        $this->info("");

        $this->info("📅 ÚLTIMAS 10 NOTIFICACIONES:");
        $recent = OrganizationNotification::query()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['organization_id', 'notifiable_id', 'notification_type', 'is_notified', 'created_at', 'notified_at']);

        $this->table(
            ['Org ID', 'Notifiable ID', 'Tipo', 'Enviada', 'Creada', 'Enviada en'],
            $recent->map(function ($record) {
                return [
                    substr($record->organization_id, 0, 8) . '...',
                    substr($record->notifiable_id, 0, 8) . '...',
                    $record->notification_type,
                    $record->is_notified ? '✅' : '⏳',
                    $record->created_at->format('Y-m-d H:i:s'),
                    $record->notified_at ? $record->notified_at->format('Y-m-d H:i:s') : '-'
                ];
            })
        );

        return 0;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotification;
use Core\Shared\Domain\Enums\NotificationType;

class CheckNotificationRecordsCommand extends Command
{
    protected $signature = 'notifications:check {--type=} {--limit=10}';
    protected $description = 'Check notification records in the database';

    public function handle()
    {
        $type = $this->option('type');
        $limit = (int) $this->option('limit');

        $this->info("🔍 Checking notification records...");

        if ($type) {
            $this->checkSpecificType($type, $limit);
        } else {
            $this->checkAllTypes();
        }
    }

    private function checkSpecificType(string $type, int $limit)
    {
        $this->info("📊 Checking notifications of type: {$type}");

        $notifications = OrganizationNotification::query()
            ->where('notification_type', $type)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($notifications->isEmpty()) {
            $this->warn("❌ No notifications found for type: {$type}");
            return;
        }

        $this->info("✅ Found {$notifications->count()} notifications of type: {$type}");

        $this->table(
            ['ID', 'Organization ID', 'Notifiable ID', 'Notifiable Type', 'Type', 'Is Notified', 'Created At'],
            $notifications->map(function ($notification) {
                return [
                    $notification->id,
                    $notification->organization_id,
                    $notification->notifiable_id,
                    $notification->notifiable_type,
                    $notification->notification_type,
                    $notification->is_notified ? 'Yes' : 'No',
                    $notification->created_at->format('Y-m-d H:i:s')
                ];
            })
        );
    }

    private function checkAllTypes()
    {
        $this->info("📊 Summary of all notification types:");

        $summary = OrganizationNotification::query()
            ->selectRaw('notification_type, COUNT(*) as total, SUM(CASE WHEN is_notified = 1 THEN 1 ELSE 0 END) as notified, SUM(CASE WHEN is_notified = 0 THEN 1 ELSE 0 END) as pending')
            ->groupBy('notification_type')
            ->get();

        if ($summary->isEmpty()) {
            $this->warn("❌ No notifications found in database");
            return;
        }

        $this->table(
            ['Type', 'Total', 'Notified', 'Pending'],
            $summary->map(function ($row) {
                return [
                    $row->notification_type,
                    $row->total,
                    $row->notified,
                    $row->pending
                ];
            })
        );

        // Check specifically for AI words notifications
        $aiWordsCount = OrganizationNotification::query()
            ->where('notification_type', NotificationType::AI_WORDS_PROCESS_ACTION->value)
            ->count();

        $this->info("🤖 AI Words notifications: {$aiWordsCount}");

        if ($aiWordsCount > 0) {
            $this->info("📋 Recent AI Words notifications:");
            $recentAiWords = OrganizationNotification::query()
                ->where('notification_type', NotificationType::AI_WORDS_PROCESS_ACTION->value)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $this->table(
                ['ID', 'Organization ID', 'Notifiable ID', 'Is Notified', 'Created At'],
                $recentAiWords->map(function ($notification) {
                    return [
                        $notification->id,
                        $notification->organization_id,
                        $notification->notifiable_id,
                        $notification->is_notified ? 'Yes' : 'No',
                        $notification->created_at->format('Y-m-d H:i:s')
                    ];
                })
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Process\Models\ProcessAction;

/**
 * One-time (and safe-to-re-run) cleanup command.
 *
 * Background:
 *   On or around June 3 2026, the Rama Judicial started publishing duplicate
 *   instances (carpetas) for the same radicado.  The daily sync at that time
 *   had no historical-notification guard, so it created an OrganizationNotification
 *   row (is_email_notified=false) for every single historical actuación it fetched
 *   from those new instances — potentially thousands of rows going back to 2000.
 *
 *   When NotificationDigestService later ran, it picked up every pending row and
 *   blasted clients with 100-2000 actuaciones in a single digest.
 *
 * What this command does:
 *   For each organization that has pending (is_email_notified=false) notifications,
 *   it looks up the latest action_date that was already successfully sent in a
 *   previous digest (is_email_notified=true).  Any pending notification whose
 *   linked action_date is on or before that "last notified date" is considered
 *   historical noise and is marked as silenced (is_email_notified=true, no digest).
 *
 *   If an organization has NO previous successful notifications (new org), it uses
 *   --fallback-date (default: yesterday) as the cutoff, so only truly new actions
 *   will be sent on the next digest run.
 *
 * Usage:
 *   php artisan notifications:purge-stale
 *   php artisan notifications:purge-stale --fallback-date=2026-06-02
 *   php artisan notifications:purge-stale --dry-run
 */
class PurgeStaleNotificationsCommand extends Command
{
    protected $signature = 'notifications:purge-stale
                            {--fallback-date= : Cutoff date (Y-m-d) for orgs with no prior successful notification. Defaults to yesterday.}
                            {--dry-run : Show what would be silenced without making changes.}';

    protected $description = 'Silence historical pending notifications that pre-date the last successfully sent digest per organization.';

    public function handle(): int
    {
        $logChannel = config('judicial-sync.log_channel', 'judicial_sync_notifications');
        $dryRun = (bool) $this->option('dry-run');

        $fallbackDate = $this->option('fallback-date')
            ? Carbon::parse($this->option('fallback-date'))->startOfDay()
            : Carbon::yesterday()->startOfDay();

        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be written to the database.');
        }

        $this->info("Fallback cutoff date for new orgs: {$fallbackDate->toDateString()}");

        $morphClass = (new ProcessAction)->getMorphClass();

        // Collect distinct organizations that have at least one pending notification
        $organizationIds = OrganizationNotification::query()
            ->where('is_email_notified', false)
            ->where('notifiable_type', $morphClass)
            ->distinct()
            ->pluck('organization_id');

        if ($organizationIds->isEmpty()) {
            $this->info('No pending notifications found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Found {$organizationIds->count()} organization(s) with pending notifications.");
        $bar = $this->output->createProgressBar($organizationIds->count());
        $bar->start();

        $totalSilenced = 0;

        foreach ($organizationIds as $organizationId) {
            // Find the most recent registration_date already included in a digest for this org.
            $lastNotifiedDate = DB::table('organization_notifications')
                ->join('process_actions', 'organization_notifications.notifiable_id', '=', 'process_actions.id')
                ->where('organization_notifications.organization_id', $organizationId)
                ->where('organization_notifications.notifiable_type', $morphClass)
                ->where('organization_notifications.is_email_notified', true)
                ->whereNotNull('organization_notifications.notification_digest_id')
                ->max('process_actions.registration_date');

            $cutoff = $lastNotifiedDate
                ? Carbon::parse($lastNotifiedDate)->startOfDay()
                : $fallbackDate;

            // Find all pending notifications for this org whose registration_date <= cutoff
            $staleIds = DB::table('organization_notifications')
                ->join('process_actions', 'organization_notifications.notifiable_id', '=', 'process_actions.id')
                ->where('organization_notifications.organization_id', $organizationId)
                ->where('organization_notifications.notifiable_type', $morphClass)
                ->where('organization_notifications.is_email_notified', false)
                ->whereDate('process_actions.registration_date', '<=', $cutoff)
                ->pluck('organization_notifications.id');

            if ($staleIds->isEmpty()) {
                $bar->advance();

                continue;
            }

            $count = $staleIds->count();

            Log::channel($logChannel)->info('PurgeStaleNotificationsCommand: silencing stale notifications', [
                'organization_id' => $organizationId,
                'cutoff' => $cutoff->toDateString(),
                'count' => $count,
                'dry_run' => $dryRun,
            ]);

            if (! $dryRun) {
                // Mark as silenced in chunks to avoid huge IN() clauses
                foreach ($staleIds->chunk(500) as $chunk) {
                    OrganizationNotification::query()
                        ->whereIn('id', $chunk)
                        ->update([
                            'is_email_notified' => true,
                            'email_notified_at' => now(),
                            'is_notified' => true,
                            'notified_at' => now(),
                        ]);
                }
            }

            $totalSilenced += $count;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $action = $dryRun ? 'Would silence' : 'Silenced';
        $this->info("{$action} {$totalSilenced} stale notification(s) across {$organizationIds->count()} organization(s).");

        return self::SUCCESS;
    }
}

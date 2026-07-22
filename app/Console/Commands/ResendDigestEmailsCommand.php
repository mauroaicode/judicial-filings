<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Application\Shared\Services\Notification\ResendDigestEmailsService;
use Src\Domain\Organization\Models\Organization;

class ResendDigestEmailsCommand extends Command
{
    protected $signature = 'judicial:resend-digest-emails
                            {--organization= : Organization UUID (required unless --all-multi-email)}
                            {--digest= : Specific digest UUID (defaults to latest of --date)}
                            {--date= : Digest date Y-m-d (defaults to today)}
                            {--include-primary : Also resend to the first/priority email (may duplicate)}
                            {--emails= : Comma-separated emails to target (optional filter)}
                            {--all-multi-email : Process all orgs that have 2+ active email channels}
                            {--dry-run : Preview recipients without sending}';

    protected $description = 'Resend an existing consolidated digest to email channels that did not receive it (default: secondary emails only).';

    public function handle(ResendDigestEmailsService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $includePrimary = (bool) $this->option('include-primary');
        $digestId = $this->option('digest');
        $onDate = $this->option('date');
        $emailsOption = $this->option('emails');

        $onlyEmails = null;
        if (is_string($emailsOption) && $emailsOption !== '') {
            $onlyEmails = array_values(array_filter(array_map('trim', explode(',', $emailsOption))));
        }

        $organizationIds = $this->resolveOrganizationIds();
        if ($organizationIds === []) {
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('[DRY RUN] No emails will be sent.');
        }

        $exitCode = self::SUCCESS;

        foreach ($organizationIds as $organizationId) {
            $org = Organization::query()->find($organizationId);
            $this->info('Organization: '.($org instanceof Organization ? $org->name : $organizationId));

            $result = $service->resend(
                organizationId: $organizationId,
                digestId: is_string($digestId) && $digestId !== '' ? $digestId : null,
                onDate: is_string($onDate) && $onDate !== '' ? $onDate : null,
                includePrimary: $includePrimary,
                onlyEmails: $onlyEmails,
                dryRun: $dryRun,
            );

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Digest ID', $result->digestId ?? '—'],
                    ['Digest created_at', $result->digestCreatedAt ?? '—'],
                    ['Sent', (string) count($result->sentTo)],
                    ['Skipped', (string) count($result->skipped)],
                    ['Failed', (string) count($result->failed)],
                ],
            );

            foreach ($result->sentTo as $email) {
                $this->line("  sent → {$email}");
            }
            foreach ($result->skipped as $line) {
                $this->warn("  skipped → {$line}");
            }
            foreach ($result->failed as $line) {
                $this->error("  failed → {$line}");
                $exitCode = self::FAILURE;
            }

            $this->newLine();
        }

        return $exitCode;
    }

    /**
     * @return list<string>
     */
    private function resolveOrganizationIds(): array
    {
        if ((bool) $this->option('all-multi-email')) {
            return Organization::query()
                ->whereHas('notificationChannels', function ($q): void {
                    $q->where('channel_type', 'email')->where('is_active', true);
                }, '>=', 2)
                ->pluck('id')
                ->all();
        }

        $organizationId = $this->option('organization');
        if (! is_string($organizationId) || $organizationId === '') {
            $this->error('Provide --organization= or --all-multi-email.');

            return [];
        }

        return [$organizationId];
    }
}

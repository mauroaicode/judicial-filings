<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Src\Application\Shared\Contracts\Notification\ImportReportChannelDriverInterface;
use Src\Application\Shared\DTOs\ProcessImportReport;
use Src\Application\Shared\Notifications\ProcessImportReportNotification;
use Src\Application\Shared\Services\Notification\Channels\EmailImportReportChannelDriver;
use Src\Application\Shared\Services\Notification\Channels\InternalImportReportChannelDriver;
use Src\Application\Shared\Services\Notification\Channels\SmsImportReportChannelDriver;
use Src\Application\Shared\Services\Notification\Channels\WhatsAppImportReportChannelDriver;
use Src\Domain\Notification\Models\HistoryOrganizationChannelNotification;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
use Src\Domain\Process\Models\ProcessImportBatch;
use Throwable;

/**
 * Orchestrates import-report notifications for both the administrator and the organization.
 *
 * Admin path : always email (ADMIN_PROCESS_IMPORT_REPORT_EMAIL) + always internal (log only).
 *              No DB audit records — admin is not an organization.
 *
 * Org path   : creates an OrganizationNotification record, dispatches the internal channel
 *              (always, regardless of is_active), then all active external channels.
 *              Each dispatch attempt is audited in HistoryOrganizationChannelNotification.
 *              OrganizationNotification.is_notified is set to true when at least one channel succeeds.
 *
 * Extensibility: add a new entry to CHANNEL_DRIVERS to support additional channels.
 */
class ImportReportNotificationService
{
    /** Notification type stored in organization_notifications. */
    private const NOTIFICATION_TYPE = 'import_report';

    /**
     * Maps channel_type values to their driver class.
     *
     * @var array<string, class-string<ImportReportChannelDriverInterface>>
     */
    private const CHANNEL_DRIVERS = [
        'email' => EmailImportReportChannelDriver::class,
        'whatsapp' => WhatsAppImportReportChannelDriver::class,
        'sms' => SmsImportReportChannelDriver::class,
    ];

    public function __construct(
        private readonly InternalImportReportChannelDriver $internalDriver,
    ) {}

    /**
     * Sends the import report to the system administrator.
     *
     * Always sends an email to the configured admin address and records an internal log event.
     * Skips the email silently when admin_report_email is not configured.
     * No DB audit records are created for admin — use application logs for traceability.
     *
     * @param  ProcessImportReport  $report  Completed import report
     */
    public function notifyAdmin(ProcessImportReport $report): void
    {
        $this->sendAdminEmail($report);

        $this->internalDriver->send($report, 'admin');
    }

    /**
     * Sends the import report to the organization through all its active channels.
     *
     * Creates an OrganizationNotification record as the audit head.
     * Internal channel is always dispatched regardless of is_active.
     * External channels are dispatched only when is_active = true.
     * Every dispatch attempt (success or failure) is recorded in HistoryOrganizationChannelNotification.
     *
     * @param  ProcessImportReport  $report  Completed import report
     * @param  string  $organizationId  Organization UUID
     */
    public function notifyOrganization(ProcessImportReport $report, string $organizationId): void
    {
        $orgNotification = $this->createOrganizationNotification($report, $organizationId);

        $atLeastOneSuccess = $this->dispatchInternalChannel($report, $orgNotification, $organizationId);

        $activeChannels = OrganizationNotificationChannel::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->whereIn('channel_type', array_keys(self::CHANNEL_DRIVERS))
            ->orderBy('priority')
            ->get();

        foreach ($activeChannels as $channel) {
            $success = $this->dispatchChannel($report, $orgNotification, $channel);

            if ($success) {
                $atLeastOneSuccess = true;
            }
        }

        if ($atLeastOneSuccess) {
            $this->markOrganizationNotified($orgNotification);
        }
    }

    /**
     * Creates the OrganizationNotification head record before any channel dispatch.
     *
     * @param  ProcessImportReport  $report  Completed import report
     * @param  string  $organizationId  Organization UUID
     */
    private function createOrganizationNotification(
        ProcessImportReport $report,
        string $organizationId,
    ): OrganizationNotification {
        return OrganizationNotification::query()->create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'notifiable_id' => $report->batchId,
            'notifiable_type' => ProcessImportBatch::class,
            'notification_type' => self::NOTIFICATION_TYPE,
            'is_notified' => false,
            'is_viewed' => false,
        ]);
    }

    /**
     * Dispatches the internal channel and records history if the channel exists in DB.
     *
     * Internal is always dispatched (ignores is_active). Returns true on success.
     *
     * @param  ProcessImportReport  $report  Completed import report
     * @param  OrganizationNotification  $orgNotification  Audit head record
     * @param  string  $organizationId  Organization UUID
     * @return bool Whether the internal dispatch succeeded
     */
    private function dispatchInternalChannel(
        ProcessImportReport $report,
        OrganizationNotification $orgNotification,
        string $organizationId,
    ): bool {
        try {
            $this->internalDriver->send($report, 'organization:'.$organizationId);

            $internalChannel = $this->findInternalChannel($organizationId);

            if ($internalChannel) {
                $this->recordHistory($orgNotification, $internalChannel, true);
            }

            return true;
        } catch (Throwable $e) {
            $this->log('Internal import report channel failed', $report, ['error' => $e->getMessage()], 'error');

            return false;
        }
    }

    /**
     * Resolves, dispatches and audits a single external channel.
     *
     * @param  ProcessImportReport  $report  Completed import report
     * @param  OrganizationNotification  $orgNotification  Audit head record
     * @param  OrganizationNotificationChannel  $channel  Active organization channel
     * @return bool Whether the dispatch succeeded
     */
    private function dispatchChannel(
        ProcessImportReport $report,
        OrganizationNotification $orgNotification,
        OrganizationNotificationChannel $channel,
    ): bool {
        $driverClass = self::CHANNEL_DRIVERS[$channel->channel_type] ?? null;

        if ($driverClass === null) {
            return false;
        }

        try {
            /** @var ImportReportChannelDriverInterface $driver */
            $driver = resolve($driverClass);
            $driver->send($report, $channel);

            $this->recordHistory($orgNotification, $channel, true);

            return true;
        } catch (Throwable $e) {
            $this->recordHistory($orgNotification, $channel, false);

            $this->log('Import report channel dispatch failed', $report, [
                'channel_id' => $channel->id,
                'channel_type' => $channel->channel_type,
                'error' => $e->getMessage(),
            ], 'error');

            return false;
        }
    }

    /**
     * Creates a HistoryOrganizationChannelNotification entry for the dispatch attempt.
     *
     * @param  OrganizationNotification  $orgNotification  Audit head record
     * @param  OrganizationNotificationChannel  $channel  Dispatched channel
     * @param  bool  $success  Whether the dispatch succeeded
     */
    private function recordHistory(
        OrganizationNotification $orgNotification,
        OrganizationNotificationChannel $channel,
        bool $success,
    ): void {
        HistoryOrganizationChannelNotification::query()->create([
            'organization_notification_channel_id' => $channel->id,
            'notifiable_id' => $orgNotification->notifiable_id,
            'notifiable_type' => $orgNotification->notifiable_type,
            'notification_type' => self::NOTIFICATION_TYPE,
            'is_notified' => $success,
            'notified_at' => $success ? now() : null,
        ]);
    }

    /**
     * Marks the OrganizationNotification as delivered after at least one channel succeeded.
     *
     * @param  OrganizationNotification  $orgNotification  Audit head record to mark
     */
    private function markOrganizationNotified(OrganizationNotification $orgNotification): void
    {
        $orgNotification->update([
            'is_notified' => true,
            'notified_at' => now(),
        ]);
    }

    /**
     * Sends the report email to the configured admin address.
     *
     * @param  ProcessImportReport  $report  Completed import report
     */
    private function sendAdminEmail(ProcessImportReport $report): void
    {
        $adminEmail = config('process-import.admin_report_email');

        if (! is_string($adminEmail) || $adminEmail === '') {
            return;
        }

        try {
            Notification::route('mail', $adminEmail)->notify(new ProcessImportReportNotification($report));

            $this->log('Admin import report email queued', $report, ['recipient' => $adminEmail]);
        } catch (Throwable $e) {
            $this->log('Admin import report email failed', $report, [
                'recipient' => $adminEmail,
                'error' => $e->getMessage(),
            ], 'error');
        }
    }

    /**
     * Returns the internal channel record for the organization, or null if not configured.
     *
     * @param  string  $organizationId  Organization UUID
     */
    private function findInternalChannel(string $organizationId): ?OrganizationNotificationChannel
    {
        return OrganizationNotificationChannel::query()
            ->where('organization_id', $organizationId)
            ->where('channel_type', 'internal')
            ->first();
    }

    /**
     * Writes a log entry to the import log channel.
     *
     * @param  string  $message  Log message
     * @param  ProcessImportReport  $report  Report for context
     * @param  array<string, mixed>  $context  Additional context data
     * @param  string  $level  PSR log level (info|error|warning)
     */
    private function log(string $message, ProcessImportReport $report, array $context = [], string $level = 'info'): void
    {
        Log::channel(config('process-import.log_channel', 'process_import'))
            ->$level($message, array_merge(['batch_id' => $report->batchId], $context));
    }
}

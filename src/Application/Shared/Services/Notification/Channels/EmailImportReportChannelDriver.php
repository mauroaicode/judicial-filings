<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Src\Application\Shared\Contracts\Notification\ImportReportChannelDriverInterface;
use Src\Application\Shared\DTOs\ProcessImportReport;
use Src\Application\Shared\Notifications\ProcessImportReportNotification;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
use Throwable;

/**
 * Sends the import report to the email address stored in channel_value.
 */
class EmailImportReportChannelDriver implements ImportReportChannelDriverInterface
{
    /**
     * Dispatches the ProcessImportReportNotification to the channel's email address.
     *
     * @param  ProcessImportReport  $report  Completed import report
     * @param  OrganizationNotificationChannel  $channel  Channel whose channel_value holds the recipient email
     *
     * @throws InvalidArgumentException|Throwable When channel_value is empty
     */
    public function send(ProcessImportReport $report, OrganizationNotificationChannel $channel): void
    {
        $recipient = $channel->channel_value;

        if (empty($recipient)) {
            throw new InvalidArgumentException('Email channel has no recipient address.');
        }

        try {
            Notification::route('mail', $recipient)->notify(new ProcessImportReportNotification($report));

            Log::channel(config('process-import.log_channel', 'process_import'))
                ->info('Import report email notification queued', [
                    'batch_id' => $report->batchId,
                    'channel_id' => $channel->id,
                    'recipient' => $recipient,
                ]);
        } catch (Throwable $e) {
            Log::channel(config('process-import.log_channel', 'process_import'))
                ->error('Import report email notification failed', [
                    'batch_id' => $report->batchId,
                    'channel_id' => $channel->id,
                    'error' => $e->getMessage(),
                ]);

            throw $e;
        }
    }
}

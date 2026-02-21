<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Contracts\Notification\ImportReportChannelDriverInterface;
use Src\Application\Shared\DTOs\ProcessImportReport;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

/**
 * Sends a short SMS with the link to the import report history.
 *
 * Full report details are available in the notification history panel.
 * Actual delivery integration (e.g. Twilio / AWS SNS) is a placeholder.
 */
class SmsImportReportChannelDriver implements ImportReportChannelDriverInterface
{
    /**
     * Dispatches the SMS import-report message to the channel's phone number.
     *
     * @param  ProcessImportReport  $report  Completed import report
     * @param  OrganizationNotificationChannel  $channel  Channel whose channel_value holds the phone number
     *
     * @throws \InvalidArgumentException When channel_value is empty
     */
    public function send(ProcessImportReport $report, OrganizationNotificationChannel $channel): void
    {
        $recipient = $channel->channel_value;

        if (empty($recipient)) {
            throw new \InvalidArgumentException('SMS channel has no recipient number.');
        }

        // TODO: integrate with real SMS provider (e.g. Twilio, AWS SNS).
        Log::channel(config('process-import.log_channel', 'process_import'))
            ->info('Import report SMS notification (placeholder)', [
                'batch_id' => $report->batchId,
                'channel_id' => $channel->id,
                'recipient' => $recipient,
                'message' => $this->buildMessage($report),
            ]);
    }

    /**
     * Builds the short SMS message text with the history link.
     *
     * @param  ProcessImportReport  $report  Completed import report
     */
    private function buildMessage(ProcessImportReport $report): string
    {
        $historyUrl = config('app.url').'/importaciones/'.$report->batchId;

        return __('process.import_report_sms_message', [
            'success' => $report->successCount,
            'total' => $report->totalCount,
            'url' => $historyUrl,
        ]);
    }
}

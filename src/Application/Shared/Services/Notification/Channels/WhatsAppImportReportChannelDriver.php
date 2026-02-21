<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Contracts\Notification\ImportReportChannelDriverInterface;
use Src\Application\Shared\DTOs\ProcessImportReport;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

/**
 * Sends a short WhatsApp message with the link to the import report history.
 *
 * Full report details are available in the notification history panel.
 * Actual delivery integration (e.g. Twilio / Meta Cloud API) is a placeholder.
 */
class WhatsAppImportReportChannelDriver implements ImportReportChannelDriverInterface
{
    /**
     * Dispatches the WhatsApp import-report message to the channel's number.
     *
     * @param  ProcessImportReport  $report  Completed import report
     * @param  OrganizationNotificationChannel  $channel  Channel whose channel_value holds the WhatsApp number
     *
     * @throws \InvalidArgumentException When channel_value is empty
     */
    public function send(ProcessImportReport $report, OrganizationNotificationChannel $channel): void
    {
        $recipient = $channel->channel_value;

        if (empty($recipient)) {
            throw new \InvalidArgumentException('WhatsApp channel has no recipient number.');
        }

        // TODO: integrate with real WhatsApp provider (e.g. Twilio, Meta Cloud API).
        Log::channel(config('process-import.log_channel', 'process_import'))
            ->info('Import report WhatsApp notification (placeholder)', [
                'batch_id' => $report->batchId,
                'channel_id' => $channel->id,
                'recipient' => $recipient,
                'message' => $this->buildMessage($report),
            ]);
    }

    /**
     * Builds the short WhatsApp message text with the history link.
     *
     * @param  ProcessImportReport  $report  Completed import report
     */
    private function buildMessage(ProcessImportReport $report): string
    {
        $historyUrl = config('app.url').'/importaciones/'.$report->batchId;

        return __('process.import_report_whatsapp_message', [
            'success' => $report->successCount,
            'total' => $report->totalCount,
            'url' => $historyUrl,
        ]);
    }
}

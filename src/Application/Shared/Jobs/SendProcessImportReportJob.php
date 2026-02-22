<?php

declare(strict_types=1);

namespace Src\Application\Shared\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Contracts\Process\ImportReportChannelInterface;
use Src\Application\Shared\DTOs\ProcessImportReport;
use Src\Application\Shared\Services\Process\Channels\DiscordImportReportChannel;
use Src\Application\Shared\Services\Process\Channels\EmailImportReportChannel;
use Src\Domain\Process\Models\ProcessImportBatch;

class SendProcessImportReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $tries = 3;

    /** @var int */
    public $timeout = 60;

    private const CHANNEL_MAP = [
        'email' => EmailImportReportChannel::class,
        'discord' => DiscordImportReportChannel::class,
    ];

    public function __construct(
        public string $processImportBatchId
    ) {}

    /**
     * @throws \Throwable
     */
    public function handle(): void
    {
        $batch = ProcessImportBatch::query()->with('organization', 'requestedByUser')->find($this->processImportBatchId);

        if (! $batch) {
            return;
        }

        $reportRecipientEmail = $batch->requestedByUser?->email;

        $report = new ProcessImportReport(
            batchId: $batch->id,
            organizationName: $batch->organization->name ?? '',
            excelTotalCount: $batch->excel_total_count,
            totalCount: $batch->total_count,
            multipleInstancesCount: $batch->multiple_instances_count,
            successCount: $batch->success_count,
            failedCount: $batch->failed_count,
            errors: $batch->errors ?? [],
            completedAt: $batch->completed_at,
            reportRecipientEmail: $reportRecipientEmail,
        );

        $channels = config('process-import.report_channels', ['email']);
        $logChannel = config('process-import.log_channel', 'process_import');

        foreach ($channels as $channelName) {
            $channelClass = self::CHANNEL_MAP[$channelName] ?? null;
            if ($channelClass === null) {
                continue;
            }

            try {
                /** @var ImportReportChannelInterface $channel */
                $channel = resolve($channelClass);
                $channel->send($report);
            } catch (\Throwable $e) {
                Log::channel($logChannel)->error('Send process import report failed', [
                    'batch_id' => $this->processImportBatchId,
                    'channel' => $channelName,
                    'message' => $e->getMessage(),
                ]);
                throw $e;
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process\Channels;

use Src\Application\Shared\Contracts\Process\ImportReportChannelInterface;
use Src\Application\Shared\DTOs\ProcessImportReport;

/**
 * Stub for sending import report to Discord.
 * Configure webhook and implement send() when the channel is ready.
 */
class DiscordImportReportChannel implements ImportReportChannelInterface
{
    public function send(ProcessImportReport $report): void
    {
        // TODO: when Discord webhook is configured, send report (e.g. HTTP post to webhook URL).
    }
}

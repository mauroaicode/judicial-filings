<?php

declare(strict_types=1);

namespace Src\Application\Shared\Contracts\Notification;

use Src\Application\Shared\DTOs\ProcessImportReport;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

interface ImportReportChannelDriverInterface
{
    /**
     * Send the import report through this channel.
     */
    public function send(ProcessImportReport $report, OrganizationNotificationChannel $channel): void;
}

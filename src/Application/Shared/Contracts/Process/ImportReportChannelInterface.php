<?php

declare(strict_types=1);

namespace Src\Application\Shared\Contracts\Process;

use Src\Application\Shared\DTOs\ProcessImportReport;

interface ImportReportChannelInterface
{
    public function send(ProcessImportReport $report): void;
}

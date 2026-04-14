<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Dashboard\Data;

use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class DashboardSummaryData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public int $total_recent_actions,
        public int $alerts_red,
        public int $alerts_yellow,
        public int $alerts_green,
    ) {}
}

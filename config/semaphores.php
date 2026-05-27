<?php

declare(strict_types=1);

return [
    // When a process has recent activity, we show it as "moving" (green) instead of "En espera".
    // This does NOT replace inactivity thresholds; it is only a default color when no inactivity alert is set.
    'moving_days_green' => 30,
    'inactivity_thresholds' => [
        'plaintiff' => [
            \Src\Domain\Shared\Enums\SeverityColor::RED->value => 90,
            \Src\Domain\Shared\Enums\SeverityColor::YELLOW->value => 45,
        ],
        'defendant' => [
            \Src\Domain\Shared\Enums\SeverityColor::GREEN->value => 90,
        ],
    ],
];

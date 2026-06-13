<?php

declare(strict_types=1);

return [
    /*
    | Days since last official activity (last_activity_date).
    |
    | Plaintiff (demandante): inactivity is bad — green < 45, yellow 45–89, red >= 90.
    | Defendant (demandado): inverted — red < 45, yellow 45–89, green >= 90.
    */
    'inactivity_thresholds' => [
        'plaintiff' => [
            \Src\Domain\Shared\Enums\SeverityColor::RED->value => 90,
            \Src\Domain\Shared\Enums\SeverityColor::YELLOW->value => 45,
        ],
        'defendant' => [
            \Src\Domain\Shared\Enums\SeverityColor::GREEN->value => 90,
            \Src\Domain\Shared\Enums\SeverityColor::YELLOW->value => 45,
        ],
    ],
];

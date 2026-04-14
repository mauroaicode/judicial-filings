<?php

declare(strict_types=1);

return [
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

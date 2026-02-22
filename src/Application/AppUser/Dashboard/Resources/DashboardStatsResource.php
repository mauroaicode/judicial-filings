<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Dashboard\Resources;

use Spatie\LaravelData\Resource;

class DashboardStatsResource extends Resource
{
    public function __construct(
        public int $total_processes,
        public int $active_processes,
        public int $inactive_processes,
        public int $processes_with_multiple_instances,
        /** @var array{by_type: array{actuacion: int, actuacion_alerta: int}} */
        public array $notifications,
    ) {}

    public static function fromCounts(
        int $totalProcesses,
        int $activeProcesses,
        int $inactiveProcesses,
        int $processesWithMultipleInstances,
        array $notificationsByType
    ): self {
        return new self(
            total_processes: $totalProcesses,
            active_processes: $activeProcesses,
            inactive_processes: $inactiveProcesses,
            processes_with_multiple_instances: $processesWithMultipleInstances,
            notifications: [
                'by_type' => $notificationsByType,
            ],
        );
    }
}

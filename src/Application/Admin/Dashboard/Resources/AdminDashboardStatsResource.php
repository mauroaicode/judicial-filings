<?php

declare(strict_types=1);

namespace Src\Application\Admin\Dashboard\Resources;

use Spatie\LaravelData\Resource;

class AdminDashboardStatsResource extends Resource
{
    public function __construct(
        public int $total_processes,
        public int $active_processes,
        public int $orphan_processes,
        public int $private_processes,
        public int $processes_with_multiple_instances,
        public int $outdated_processes,
        public int $critical_alert_processes,
        public int $early_attention_processes,
    ) {}

    public static function fromCounts(
        int $totalProcesses,
        int $activeProcesses,
        int $orphanProcesses,
        int $privateProcesses,
        int $processesWithMultipleInstances,
        int $outdatedProcesses,
        int $criticalAlertProcesses,
        int $earlyAttentionProcesses,
    ): self {
        return new self(
            total_processes: $totalProcesses,
            active_processes: $activeProcesses,
            orphan_processes: $orphanProcesses,
            private_processes: $privateProcesses,
            processes_with_multiple_instances: $processesWithMultipleInstances,
            outdated_processes: $outdatedProcesses,
            critical_alert_processes: $criticalAlertProcesses,
            early_attention_processes: $earlyAttentionProcesses,
        );
    }
}

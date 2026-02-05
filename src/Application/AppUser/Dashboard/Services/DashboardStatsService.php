<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Dashboard\Services;

use Src\Application\AppUser\Dashboard\Resources\DashboardStatsResource;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;

readonly class DashboardStatsService
{
    public function handle(string $organizationId): DashboardStatsResource
    {
        $processCounts = $this->getProcessCounts($organizationId);
        $notificationCountsByType = $this->getNotificationCountsByType($organizationId);

        return DashboardStatsResource::fromCounts(
            totalProcesses: $processCounts['total'],
            activeProcesses: $processCounts['active'],
            inactiveProcesses: $processCounts['inactive'],
            notificationsByType: $notificationCountsByType
        );
    }

    /**
     * @return array{total: int, active: int, inactive: int}
     */
    private function getProcessCounts(string $organizationId): array
    {
        $total = OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->count();

        $active = OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->count();

        $inactive = $total - $active;

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
        ];
    }

    /**
     * @return array{actuacion: int, actuacion_alerta: int}
     */
    private function getNotificationCountsByType(string $organizationId): array
    {
        $counts = OrganizationNotification::query()
            ->whereOrganization($organizationId)
            ->whereUnviewed()
            ->selectRaw('notification_type, count(*) as count')
            ->groupBy('notification_type')
            ->pluck('count', 'notification_type')
            ->all();

        return [
            'actuacion' => (int) ($counts['actuacion'] ?? 0),
            'actuacion_alerta' => (int) ($counts['actuacion_alerta'] ?? 0),
        ];
    }
}

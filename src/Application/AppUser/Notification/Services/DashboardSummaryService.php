<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Date;
use Src\Application\AppUser\Notification\Data\DashboardSummaryData;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Models\ProcessAction;

readonly class DashboardSummaryService
{
    /**
     * Get the summary statistics for the dashboard.
     *
     * @param  string  $organizationId  The organization ID.
     * @param  string|null  $dateFrom  Optional start date filter for actions.
     * @param  string|null  $dateTo  Optional end date filter for actions.
     */
    public function handle(string $organizationId, ?string $dateFrom = null, ?string $dateTo = null): DashboardSummaryData
    {

        $totalActionsQuery = ProcessAction::query()
            ->whereHas('process.organizations', function (Builder $q) use ($organizationId): void {
                $q->where('organizations.id', $organizationId);
            });

        if ($dateFrom) {
            $totalActionsQuery->where('action_date', '>=', Date::parse($dateFrom)->startOfDay());
        }

        if ($dateTo) {
            $totalActionsQuery->where('action_date', '<=', Date::parse($dateTo)->endOfDay());
        }

        $totalActions = $totalActionsQuery->count();

        $alerts = OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->whereNotNull('inactivity_alert_level')
            ->selectRaw('inactivity_alert_level, count(*) as count')
            ->groupBy('inactivity_alert_level')
            ->pluck('count', 'inactivity_alert_level');

        return new DashboardSummaryData(
            total_actions: $totalActions,
            alerts_red: (int) ($alerts['red'] ?? 0),
            alerts_yellow: (int) ($alerts['yellow'] ?? 0),
            alerts_green: (int) ($alerts['green'] ?? 0),
        );
    }
}

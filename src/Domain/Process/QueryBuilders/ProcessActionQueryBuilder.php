<?php

declare(strict_types=1);

namespace Src\Domain\Process\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Src\Application\AppUser\Process\Data\ProcessActionFilterData;

/**
 * @extends Builder<\Src\Domain\Process\Models\ProcessAction>
 */
class ProcessActionQueryBuilder extends Builder
{
    /**
     * Filter actions by process ID.
     *
     * @return $this
     */
    public function whereProcess(string $processId): self
    {
        return $this->where('process_id', $processId);
    }

    /**
     * Filter actions by action registration ID.
     *
     * @return $this
     */
    public function whereActionRegistrationId(int $actionRegistrationId): self
    {
        return $this->where('action_registration_id', $actionRegistrationId);
    }

    /**
     * Filter actions by process and action registration ID.
     *
     * @return $this
     */
    public function whereProcessAndRegistrationId(string $processId, int $actionRegistrationId): self
    {
        return $this->whereProcess($processId)
            ->whereActionRegistrationId($actionRegistrationId);
    }

    /**
     * Order actions by action date (most recent first).
     *
     * @return $this
     */
    public function orderedByActionDate(): self
    {
        return $this->latest('action_date');
    }

    /**
     * Order actions by registration date (most recent first).
     *
     * @return $this
     */
    public function orderedByRegistrationDate(): self
    {
        return $this->latest('registration_date');
    }

    /**
     * Apply filters to the process action query.
     *
     * @param  ProcessActionFilterData  $data  The filtering criteria.
     * @return $this
     */
    public function filters(ProcessActionFilterData $data): self
    {
        $this->applyActionDateFilter($data->action_date_from, $data->action_date_to);
        $this->applyRegistrationDateFilter($data->registration_date_from, $data->registration_date_to);
        $this->applySearchFilter($data->search);
        $this->applyAlertSlugFilter($data->alert_slug);

        return $this;
    }

    /**
     * Apply action date filter (date range).
     */
    private function applyActionDateFilter(?string $from, ?string $to): void
    {
        $this->applyDateRangeFilter('action_date', $from, $to);
    }

    /**
     * Apply registration date filter (date range).
     */
    private function applyRegistrationDateFilter(?string $from, ?string $to): void
    {
        $this->applyDateRangeFilter('registration_date', $from, $to);
    }

    /**
     * Apply search filter (searches in action and annotation fields).
     */
    private function applySearchFilter(?string $search): void
    {
        if (! $search) {
            return;
        }

        $this->where(function (\Illuminate\Contracts\Database\Query\Builder $query) use ($search): void {
            $query->where('action', 'LIKE', "%{$search}%")
                ->orWhere('annotation', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Filter actions that have the given alert keyword (by slug) via process_action_alert_action_keyword.
     */
    private function applyAlertSlugFilter(?string $alertSlug): void
    {
        if (! $alertSlug || trim($alertSlug) === '') {
            return;
        }

        $this->whereHas('alertActionKeywords', function (\Illuminate\Contracts\Database\Query\Builder $q) use ($alertSlug): void {
            $q->where('slug', trim($alertSlug));
        });
    }

    /**
     * Apply date range filter helper.
     *
     * @param  string  $column  The column name to filter.
     * @param  string|null  $from  Start date.
     * @param  string|null  $to  End date.
     */
    private function applyDateRangeFilter(string $column, ?string $from, ?string $to): void
    {
        if (! $from && ! $to) {
            return;
        }

        if ($from && $to) {
            $this->whereBetween($column, [
                Date::parse($from)->startOfDay(),
                Date::parse($to)->endOfDay(),
            ]);

            return;
        }

        if ($from) {
            $this->where($column, '>=', Date::parse($from)->startOfDay());

            return;
        }

        // $to is set (we already checked !$from && !$to at the beginning)
        $this->where($column, '<=', Date::parse($to)->endOfDay());
    }
}

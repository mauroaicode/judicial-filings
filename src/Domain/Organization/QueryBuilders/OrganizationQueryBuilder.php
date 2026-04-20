<?php

declare(strict_types=1);

namespace Src\Domain\Organization\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Src\Application\Admin\Organization\Data\OrganizationFilterData;
use Src\Domain\Organization\Models\Organization;

/**
 * @extends Builder<Organization>
 */
class OrganizationQueryBuilder extends Builder
{
    /**
     * Include relationships for listing.
     *
     * @return $this
     */
    public function withRelations(): self
    {
        return $this->with(['appUsers'])
            ->withExists(['notificationChannels as is_receiving_notifications' => function ($query): void {
                $query->whereIn('channel_type', ['email', 'whatsapp', 'sms', 'internal'])
                    ->where('is_active', true);
            }]);
    }

    /**
     * Order by created_at descending.
     *
     * @return $this
     */
    public function orderedByCreatedAt(): self
    {
        $this->latest();

        return $this;
    }

    /**
     * Apply filters to the organization query.
     *
     * @return $this
     */
    public function filters(OrganizationFilterData $data): self
    {
        $this->applyNameFilter($data->name);
        $this->applyTypeFilter($data->type);
        $this->applyEmailFilter($data->email);
        $this->applyIsActiveFilter($data->is_active);
        $this->applyCreatedAtRangeFilter($data->created_at_from, $data->created_at_to);

        return $this;
    }

    private function applyNameFilter(?string $name): void
    {
        if ($name === null || $name === '') {
            return;
        }

        $this->where('name', 'LIKE', '%'.$name.'%');
    }

    private function applyTypeFilter(?string $type): void
    {
        if ($type === null || $type === '') {
            return;
        }

        $this->where('type', $type);
    }

    private function applyEmailFilter(?string $email): void
    {
        if ($email === null || $email === '') {
            return;
        }

        $this->where('email', 'LIKE', '%'.$email.'%');
    }

    private function applyIsActiveFilter(?string $isActive): void
    {
        if ($isActive === null || $isActive === '') {
            return;
        }

        $value = $isActive === 'active';

        $this->where('is_active', $value);
    }

    private function applyCreatedAtRangeFilter(?string $from, ?string $to): void
    {
        if (! $from && ! $to) {
            return;
        }

        if ($from && $to) {
            $this->whereBetween('created_at', [
                Date::parse($from)->startOfDay(),
                Date::parse($to)->endOfDay(),
            ]);

            return;
        }

        if ($from) {
            $this->whereDate('created_at', '>=', Date::parse($from)->format('Y-m-d'));
        }

        if ($to) {
            $this->whereDate('created_at', '<=', Date::parse($to)->format('Y-m-d'));
        }
    }
}

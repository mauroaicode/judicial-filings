<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Src\Application\Admin\Process\Data\AdminManualRegistrationRequestFilterData;
use Src\Domain\Process\Enums\ManualRegistrationRequestStatus;
use Src\Domain\Process\Models\ManualRegistrationRequest;

/**
 * Paginated queue of manual registration requests for ops (Edwin / admin).
 */
readonly class AdminListManualRegistrationRequestsService
{
    /**
     * @return LengthAwarePaginator<int, ManualRegistrationRequest>
     */
    public function handle(AdminManualRegistrationRequestFilterData $filters): LengthAwarePaginator
    {
        $query = ManualRegistrationRequest::query()
            ->with(['appUser', 'organization'])
            ->latest();

        $status = $filters->status ?? ManualRegistrationRequestStatus::Pending->value;
        $query->where('status', $status);

        if ($filters->reason !== null && $filters->reason !== '') {
            $query->where('reason', $filters->reason);
        }

        if ($filters->process_number !== null && $filters->process_number !== '') {
            $digits = preg_replace('/\D+/', '', $filters->process_number) ?? '';
            if ($digits !== '') {
                $query->where('process_number', 'like', '%'.$digits.'%');
            }
        }

        if ($filters->organization !== null && $filters->organization !== '') {
            $term = $filters->organization;
            $query->whereHas('organization', function (Builder $orgQuery) use ($term): void {
                $orgQuery->where('name', 'like', '%'.$term.'%');
            });
        }

        return $query->paginate($filters->per_page);
    }
}

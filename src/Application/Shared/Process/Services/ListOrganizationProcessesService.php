<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Services;

use Illuminate\Support\Collection;
use Src\Domain\Process\Models\Process;

class ListOrganizationProcessesService
{
    /**
     * List active processes for an organization with optional filters using QueryBuilder.
     *
     * @param  array<string, mixed>  $filters
     */
    public function handle(string $organizationId, array $filters): Collection
    {
        return Process::query()
            ->whereActiveForOrganization($organizationId)
            ->when(isset($filters['process_number']), function ($query) use ($filters): void {
                $query->whereProcessNumberLike($filters['process_number']);
            })
            ->when(isset($filters['court']), function ($query) use ($filters): void {
                $query->whereCourtLike($filters['court']);
            })
            ->orderByOrganizationRegistration($organizationId)
            ->get();
    }
}

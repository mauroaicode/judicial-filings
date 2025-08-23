<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Application\Actions;

use Core\BoundedContext\Customer\Process\Domain\Repositories\ProcessRepositoryInterface;
use Core\Shared\Domain\Repositories\OrganizationRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

readonly class GetFilingNumbersToProcessUseCase
{
    public function __construct(
        private ProcessRepositoryInterface $processRepository,
        private OrganizationRepositoryInterface $organizationRepository
    ) {}

    /**
     * Obtiene todos los radicados únicos con organizaciones activas
     */
    public function getAllUniqueProcessNumbersWithActiveOrganizations(): Collection
    {
        Log::channel('judicial_process_sync_job')->info('Consultando todos los radicados con organizaciones activas...');
        return $this->processRepository->getAllUniqueProcessNumbersWithActiveOrganizations();
    }

    /**
     * Obtiene los radicados únicos de una organización específica
     */
    public function getFilingsByOrganization(string $organizationSlug): Collection
    {
        $organization = $this->organizationRepository->findSlug($organizationSlug);

        if (!$organization) {
            Log::channel('judicial_process_sync_job')->warning("No se encontró la organización con el slug: {$organizationSlug}");
            return collect();
        }

        $processes = $this->processRepository->findByOrganization($organization->id);
        return $processes->pluck('process_number')->unique();
    }
}

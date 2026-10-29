<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\Admin\Organization\Data\OrganizationNotificationDigestFilterData;
use Src\Application\Admin\Organization\Services\GetOrganizationNotificationDigestDetailsService;
use Src\Application\Admin\Organization\Services\GetOrganizationPendingDigestDetailsService;
use Src\Application\AppUser\Notification\Services\ExportNotificationDigestPdfService;
use Src\Domain\Organization\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

readonly class OrganizationNotificationDigestController
{
    public function __construct(
        private GetOrganizationPendingDigestDetailsService $getOrganizationPendingDigestDetailsService,
        private GetOrganizationNotificationDigestDetailsService $getOrganizationNotificationDigestDetailsService,
        private ExportNotificationDigestPdfService $exportNotificationDigestPdfService,
    ) {}

    /**
     * Detail of the pending consolidado that would be sent for the organization
     * (digest-packages drill-down). Does not send or mark notifications.
     */
    public function pending(
        Organization $organization,
        OrganizationNotificationDigestFilterData $filters,
    ): LengthAwarePaginator {
        return $this->getOrganizationPendingDigestDetailsService->handle($organization, $filters);
    }

    /**
     * Detail of a persisted consolidado belonging to the organization.
     */
    public function show(
        Organization $organization,
        string $digest,
        OrganizationNotificationDigestFilterData $filters,
    ): LengthAwarePaginator {
        return $this->getOrganizationNotificationDigestDetailsService->handle(
            $organization,
            $digest,
            $filters,
        );
    }

    /**
     * Download the same consolidado PDF used by the app user, scoped to this organization.
     */
    public function exportPdf(Organization $organization, string $digest): Response
    {
        return $this->exportNotificationDigestPdfService->handle($organization->id, $digest);
    }
}

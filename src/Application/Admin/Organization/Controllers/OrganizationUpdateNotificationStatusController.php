<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Application\Admin\Organization\Services\OrganizationNotificationStatusService;
use Src\Domain\Organization\Models\Organization;

readonly class OrganizationUpdateNotificationStatusController
{
    /**
     * Update the status of all notification channels for an organization.
     */
    public function __invoke(
        Organization $organization,
        Request $request,
        OrganizationNotificationStatusService $service
    ): Response {
        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $service->handle($organization, (bool) $request->input('is_active'));

        return response()->noContent();
    }
}

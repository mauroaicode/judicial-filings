<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Src\Application\Admin\Organization\Services\OrganizationActiveStatusService;
use Src\Domain\Organization\Enums\OrganizationStatusMode;
use Src\Domain\Organization\Models\Organization;

readonly class OrganizationUpdateActiveStatusController
{
    /**
     * Set the organization status mode (active, inactive, or tracking_paused).
     */
    public function __invoke(
        Organization $organization,
        Request $request,
        OrganizationActiveStatusService $service
    ): Response {
        $request->validate([
            'mode' => ['required', 'string', Rule::enum(OrganizationStatusMode::class)],
            'notify_users' => ['sometimes', 'boolean'],
        ]);

        /** @var OrganizationStatusMode $mode */
        $mode = OrganizationStatusMode::from((string) $request->input('mode'));

        $service->handle(
            $organization,
            $mode,
            (bool) $request->boolean('notify_users'),
        );

        return response()->noContent();
    }
}

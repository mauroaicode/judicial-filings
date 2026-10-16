<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Services;

use Illuminate\Support\Facades\DB;
use Src\Application\Shared\Services\Notification\NotifyOrganizationUsersDeactivatedService;
use Src\Domain\Organization\Enums\OrganizationStatusMode;
use Src\Domain\Organization\Models\Organization;

class OrganizationActiveStatusService
{
    public function __construct(
        private readonly OrganizationNotificationStatusService $notificationStatusService,
        private readonly NotifyOrganizationUsersDeactivatedService $notifyOrganizationUsersDeactivatedService,
    ) {}

    /**
     * Apply a product-level organization status mode.
     *
     * - active: login + process tracking + notification channels on
     * - inactive: login blocked, tracking off, channels off
     * - tracking_paused: login allowed, tracking off, channels off
     *
     * Shared processes keep syncing for other interested active orgs.
     * Organization_processes pivots are left unchanged; tracking is gated by
     * organizations.is_process_tracking_enabled (+ is_active).
     */
    public function handle(
        Organization $organization,
        OrganizationStatusMode $mode,
        bool $notifyUsers = false,
    ): void {
        DB::transaction(function () use ($organization, $mode, $notifyUsers): void {
            $organization->update([
                'is_active' => $mode->allowsLogin(),
                'is_process_tracking_enabled' => $mode->allowsProcessTracking(),
            ]);

            $this->notificationStatusService->handle(
                $organization,
                $mode->allowsProcessTracking(),
            );

            if ($notifyUsers && $mode !== OrganizationStatusMode::Active) {
                DB::afterCommit(function () use ($organization, $mode): void {
                    $this->notifyOrganizationUsersDeactivatedService->handle(
                        $organization->fresh() ?? $organization,
                        $mode,
                    );
                });
            }
        });
    }
}

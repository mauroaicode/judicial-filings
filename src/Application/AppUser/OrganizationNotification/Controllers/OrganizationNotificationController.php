<?php

declare(strict_types=1);

namespace Src\Application\AppUser\OrganizationNotification\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\AppUser\OrganizationNotification\Data\MarkAllOrganizationNotificationsViewedData;
use Src\Application\AppUser\OrganizationNotification\Data\MarkOrganizationNotificationsViewedData;
use Src\Application\AppUser\OrganizationNotification\Data\OrganizationNotificationIndexData;
use Src\Application\AppUser\OrganizationNotification\Services\MarkAllOrganizationNotificationsViewedService;
use Src\Application\AppUser\OrganizationNotification\Services\MarkOrganizationNotificationsViewedService;
use Src\Application\AppUser\OrganizationNotification\Services\OrganizationNotificationListService;
use Src\Domain\AppUser\Models\AppUser;

readonly class OrganizationNotificationController
{
    public function __construct(
        private OrganizationNotificationListService $listService,
        private MarkOrganizationNotificationsViewedService $markViewedService,
        private MarkAllOrganizationNotificationsViewedService $markAllViewedService
    ) {}

    public function index(): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $data = OrganizationNotificationIndexData::validateAndCreate(request()->query());

        $resource = $this->listService->handle(
            $organization->id,
            $data->type, // actuacion, actuacion_alerta
            $data->viewed,
            $data->per_page,
            $data->page
        );

        return response()->json($resource->toArray());
    }

    public function markViewed(MarkOrganizationNotificationsViewedData $data): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $notificationIds = $data->getNotificationIds();
        $result = $this->markViewedService->handle($organization->id, $notificationIds);

        return response()->json($result);
    }

    public function markAllViewed(): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $type = request()->query('type') ?? request()->input('type');
        if ($type !== null && $type !== '') {
            MarkAllOrganizationNotificationsViewedData::validateAndCreate(['type' => $type]);
        }

        $result = $this->markAllViewedService->handle($organization->id, $type);

        return response()->json($result);
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\AppUser\Notification\Data\NotificationDigestFilterData;
use Src\Application\AppUser\Notification\Resources\NotificationDigestResource;
use Src\Application\AppUser\Notification\Services\NotificationDigestFinderService;
use Src\Application\AppUser\Notification\Services\GetNotificationDigestDetailsService;
use Src\Application\AppUser\Notification\Services\ListNotificationDigestHistoryService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Process\Services\GroupProcessActionsService;

readonly class NotificationDigestController
{
    public function __construct(
        private NotificationDigestFinderService $notificationDigestFinderService,
        private ListNotificationDigestHistoryService $listNotificationDigestHistoryService,
        private GroupProcessActionsService $groupProcessActionsService,
        private GetNotificationDigestDetailsService $getNotificationDigestDetailsService
    ) {}

    public function show(string $id, NotificationDigestFilterData $filters): LengthAwarePaginator
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        return $this->getNotificationDigestDetailsService->handle($organization->id, $id, $filters);
    }

    public function index(NotificationDigestFilterData $filters): LengthAwarePaginator
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        /** @var LengthAwarePaginator $paginatedDigests */
        $paginatedDigests = $this->notificationDigestFinderService->handle($filters, $organization->id, $filters->per_page);

        $mergedCollection = $paginatedDigests->getCollection()
            ->groupBy(fn ($digest) => $digest->created_at->format('Y-m-d'))
            ->map(function ($group) use ($filters): array {

                /** @var NotificationDigest $mergedDigest */
                $mergedDigest = $group->first();

                if ($group->count() > 1) {
                    $combinedData = [];
                    $combinedNotifications = collect();

                    foreach ($group as $digest) {
                        $combinedData = array_merge($combinedData, is_array($digest->data) ? $digest->data : []);
                        if ($digest->relationLoaded('notifications')) {
                            $combinedNotifications = $combinedNotifications->merge($digest->notifications);
                        }
                    }

                    $mergedDigest->data = $combinedData;
                    $mergedDigest->setRelation('notifications', $combinedNotifications);
                }

                $resource = NotificationDigestResource::fromModel($mergedDigest, $filters)->toArray();

                // 1. Limpiamos duplicados (para datos viejos en la DB)
                // Solo agrupamos si son REALMENTE la misma actuación (mismo radicado, texto, fecha y anotación)
                if (isset($resource['data']) && is_array($resource['data'])) {
                    $resource['data'] = collect($resource['data'])
                        ->groupBy(function (array $item) {
                            $id = $item['process_action_id'] ?? '';
                            $radicado = $item['process_number'] ?? '';
                            $text = $item['action_text'] ?? '';
                            $date = $item['action_date'] ?? '';
                            $annotation = $item['annotation'] ?? '';

                            // Si tiene ID, agrupamos por ID. Si no, por la combinación única.
                            return $id ?: md5($radicado.$text.$date.$annotation);
                        })
                        ->map(function (\Illuminate\Support\Collection $group) {
                            if ($group->count() === 1) {
                                return $group->first();
                            }

                            $first = $group->first();

                            // Combinamos el estado de alerta: si alguno es true, el resultado es true
                            $first['is_alert'] = $group->contains('is_alert', true);

                            // Combinamos los matched_keywords de forma limpia
                            $keywords = $group->pluck('matched_keywords')
                                ->filter()
                                ->flatMap(fn ($k): array => explode(',', (string) $k))
                                ->map(fn ($item): string => trim($item))
                                ->filter()
                                ->unique()
                                ->values();

                            $first['matched_keywords'] = $keywords->isEmpty() ? null : $keywords->implode(', ');

                            return $first;
                        })
                        ->values()
                        ->all();
                }

                // 2. Agrupamos las actuaciones para vincular Fijaciones con Autos
                if (isset($resource['data']) && count($resource['data']) > 0) {
                    $resource['data'] = $this->groupProcessActionsService->handle(collect($resource['data']))->values()->all();
                }

                // 3. Ordenamos por registration_date descendente (más recientes primero)
                if (isset($resource['data']) && count($resource['data']) > 0) {
                    $resource['data'] = collect($resource['data'])
                        ->sortByDesc(function (array $item) {
                            // Convertimos la fecha formateada a un timestamp para ordenar
                            // Usamos una variable para evitar que Rector fuerce el cambio a Date:: Facade
                            $carbon = \Carbon\Carbon::class;
                            try {
                                return $carbon::createFromLocaleFormat('d !de F !de Y', 'es', $item['registration_date'] ?? '');
                            } catch (\Exception) {
                                return $item['registration_date'] ?? '';
                            }
                        })
                        ->values()
                        ->all();
                }

                return $resource;
            })
            ->values();

        $paginatedDigests->setCollection($mergedCollection);

        return $paginatedDigests;
    }

    public function history(NotificationDigestFilterData $filters): LengthAwarePaginator
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $digests = $this->listNotificationDigestHistoryService->handle($organization->id, $filters);

        $digests->through(fn($digest) => \Src\Application\AppUser\Notification\Resources\NotificationDigestHistoryResource::fromModel($digest));

        return $digests;
    }
}

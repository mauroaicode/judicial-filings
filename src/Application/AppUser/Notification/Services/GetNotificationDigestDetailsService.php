<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Services;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Src\Application\AppUser\Notification\Data\NotificationDigestFilterData;
use Src\Application\AppUser\Notification\Resources\NotificationDigestResource;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Process\Services\GroupProcessActionsService;

class GetNotificationDigestDetailsService
{
    public function __construct(
        private readonly GroupProcessActionsService $groupProcessActionsService
    ) {}

    public function handle(string $organizationId, string $digestId, NotificationDigestFilterData $filters): LengthAwarePaginator
    {
        $digest = $this->findDigest($organizationId, $digestId);

        $resource = NotificationDigestResource::fromModel($digest, $filters)->toArray();

        $resource['data'] = collect($resource['data'] ?? []);

        $resource['data'] = $this->removeDuplicates(
            $resource['data']
        );

        $resource['data'] = $this->groupProcessActions(
            $resource['data']
        );

        $resource['data'] = $this->sortByRegistrationDate(
            $resource['data']
        )->all();

        $totalActions = count($resource['data']);
        $resource['actions_count'] = $totalActions;

        $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
        $perPage = $filters->per_page ?: 20;

        $pagedActions = array_slice($resource['data'], ($currentPage - 1) * $perPage, $perPage);
        $resource['data'] = $pagedActions;

        // Calculamos los campos "from" y "to" correctos basados en las actuaciones internas (no en el wrapper)
        $paginator = new class([$resource], $totalActions, $perPage, $currentPage, ['path' => request()->url(), 'query' => array_merge(request()->query(), $filters->toArray())]) extends LengthAwarePaginator
        {
            public function toArray()
            {
                $array = parent::toArray();

                // Extraemos cuántas actuaciones reales vinieron en esta página
                $actualItemsCount = count(func_num_args() === 0 ? $this->items->first()['data'] ?? [] : []);

                // Recalculamos matemáticamente from y to
                if ($actualItemsCount > 0) {
                    $array['from'] = ($this->currentPage() - 1) * $this->perPage() + 1;
                    $array['to'] = $array['from'] + $actualItemsCount - 1;
                } else {
                    $array['from'] = null;
                    $array['to'] = null;
                }

                return $array;
            }
        };

        return $paginator;
    }

    private function findDigest(string $organizationId, string $digestId): NotificationDigest
    {
        /** @var NotificationDigest $digest */
        $digest = NotificationDigest::query()
            ->whereOrganization($organizationId)
            ->where('id', $digestId)
            ->firstOrFail();

        return $digest;
    }

    private function removeDuplicates(Collection $data): Collection
    {
        if ($data->isEmpty()) {
            return collect();
        }

        return $data->groupBy(function (array $item): string {
            $id = $item['process_action_id'] ?? '';
            $radicado = $item['process_number'] ?? '';
            $text = $item['action_text'] ?? '';
            $date = $item['action_date'] ?? '';
            $annotation = $item['annotation'] ?? '';

            return md5($radicado.$text.$date.$annotation);
        })
            ->map(function (Collection $group) {
                if ($group->count() === 1) {
                    return $group->first();
                }

                $first = $group->first();
                $first['is_alert'] = $group->contains('is_alert', true);

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
            ->values();
    }

    private function groupProcessActions(Collection $data): Collection
    {
        if ($data->isEmpty()) {
            return collect();
        }

        // 1. Relacionamos las actuaciones (añade fijacion_action_id y notified_action_id)
        $tagged = $this->groupProcessActionsService->handle($data);

        $toRemove = collect();
        $merged = $tagged->map(function (array $item) use ($tagged, $toRemove): array {
            // Si esto es un Auto que ya fue absorbido por una fijación, lo ignoramos guardando su ID para remover
            if (isset($item['fijacion_action_id']) && $tagged->contains('process_action_id', $item['fijacion_action_id'])) {
                $toRemove->push($item['process_action_id'] ?? $item['id']);
            }

            // Si esto es un Estado/Fijación con un Auto enlazado, jalamos el texto del auto hacia acá
            if (isset($item['notified_action_id'])) {
                $auto = $tagged->firstWhere('process_action_id', $item['notified_action_id']);

                if ($auto) {
                    $item['is_merged'] = true;
                    $item['linked_action_text'] = $auto['action_text'] ?? '';
                    $item['linked_annotation'] = $auto['annotation'] ?? '';

                    // Mezclamos el estatus de alerta
                    $item['is_alert'] = ($item['is_alert'] ?? false) || ($auto['is_alert'] ?? false);

                    // Unimos keywords
                    $item['matched_keywords'] = collect([$item['matched_keywords'] ?? '', $auto['matched_keywords'] ?? ''])
                        ->filter()
                        ->unique()
                        ->implode(', ');
                }
            }

            return $item;
        });

        // 2. Removemos los Autos huérfanos que ahora son parte de un Estado
        return $merged->reject(fn (array $item): bool => ! empty($item['process_action_id']) && $toRemove->contains($item['process_action_id']))->values();
    }

    private function sortByRegistrationDate(Collection $data): Collection
    {
        if ($data->isEmpty()) {
            return collect();
        }

        return $data->sortByDesc(function (array $item) {
            $carbon = Carbon::class;
            try {
                return $carbon::createFromLocaleFormat('d !de F !de Y', 'es', $item['registration_date'] ?? '');
            } catch (\Exception) {
                return $item['registration_date'] ?? '';
            }
        })
            ->values();
    }
}

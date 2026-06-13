<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Src\Application\AppUser\Notification\Data\NotificationDigestFilterData;
use Src\Application\AppUser\Notification\Resources\NotificationDigestResource;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Process\Models\ProcessAction;

class GetNotificationDigestDetailsService
{
    public function handle(string $organizationId, string $digestId, NotificationDigestFilterData $filters): LengthAwarePaginator
    {
        $digest = $this->findDigest($organizationId, $digestId);
        $rawData = $digest->data;

        // Cheap in-memory pipeline on stored JSON (no DB enrichment yet).
        $items = NotificationDigestResource::filterRawItems($rawData, $filters);
        $items = $this->attachMissingProcessActionIds($digest, $items);
        $items = $this->removeDuplicates(collect($items))->values()->all();
        // Rows were already grouped/merged when the digest was persisted.
        $items = NotificationDigestResource::sortRawItems($items);

        $totalActions = count($items);
        $currentPage = max(1, (int) \Illuminate\Pagination\Paginator::resolveCurrentPage());
        $perPage = max(1, $filters->per_page ?: 20);
        $pageItems = array_slice($items, ($currentPage - 1) * $perPage, $perPage);

        $enrichedPage = NotificationDigestResource::formatItemsForPage(
            $digest,
            $pageItems,
            $organizationId,
        );

        $resource = array_merge(
            NotificationDigestResource::buildMetadata($digest, $totalActions),
            ['data' => $enrichedPage],
        );

        return new class([$resource], $totalActions, $perPage, $currentPage, ['path' => request()->url(), 'query' => array_merge(request()->query(), $filters->toArray())]) extends LengthAwarePaginator
        {
            public function toArray()
            {
                $array = parent::toArray();
                $actualItemsCount = count($this->items->first()['data'] ?? []);

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
            $id = (string) ($item['process_action_id'] ?? '');
            $radicado = $item['process_number'] ?? '';
            $text = $item['action_text'] ?? '';
            $date = $item['action_date'] ?? '';
            $annotation = $item['annotation'] ?? '';

            return $id !== '' ? $id : md5($radicado.$text.$date.$annotation);
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

    /**
     * Legacy digests may not persist process_action_id in JSON; resolve from linked notifications.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function attachMissingProcessActionIds(NotificationDigest $digest, array $items): array
    {
        $needsAttachment = collect($items)->contains(
            static fn (array $item): bool => empty($item['process_action_id']),
        );

        if (! $needsAttachment) {
            return $items;
        }

        $queues = [];
        $morphClass = (new ProcessAction)->getMorphClass();

        OrganizationNotification::query()
            ->where('notification_digest_id', $digest->id)
            ->where('notifiable_type', $morphClass)
            ->with([
                'notifiable' => fn ($q) => $q->select('id', 'process_id', 'action'),
                'notifiable.process' => fn ($q) => $q->select('id', 'process_number'),
            ])
            ->get()
            ->each(function (OrganizationNotification $notif) use (&$queues): void {
                $action = $notif->notifiable;
                if (! $action instanceof ProcessAction) {
                    return;
                }

                $processNumber = $action->process->process_number ?? '';
                $key = "{$processNumber}|{$action->action}";
                $queues[$key][] = $action->id;
            });

        return array_map(static function (array $item) use (&$queues): array {
            if (! empty($item['process_action_id'])) {
                return $item;
            }

            $key = ($item['process_number'] ?? '').'|'.($item['action_text'] ?? '');
            if ($key !== '|' && (isset($queues[$key]) && $queues[$key] !== [])) {
                $item['process_action_id'] = array_shift($queues[$key]);
            }

            return $item;
        }, $items);
    }
}

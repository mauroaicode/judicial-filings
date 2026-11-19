<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Services;

use Illuminate\Support\Collection;

/**
 * When fijación↔auto are paired, the FE often hides one row and only reads
 * documents from the visible one. Merge partner documents onto each page row
 * so admin/app-user both see PDFs regardless of which side they were uploaded to.
 */
readonly class MergePairedActionDocumentsService
{
    /**
     * @param  Collection<int, array<string, mixed>>  $pageItems
     * @param  Collection<int, array<string, mixed>>|null  $contextItems
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(Collection $pageItems, ?Collection $contextItems = null): Collection
    {
        /** @var Collection<string, array<string, mixed>> $byId */
        $byId = $pageItems
            ->concat($contextItems ?? collect())
            ->filter(fn (array $item): bool => isset($item['id']) && is_string($item['id']))
            ->keyBy('id');

        return $pageItems->map(function (array $item) use ($byId): array {
            $partnerId = $item['notified_action_id'] ?? $item['fijacion_action_id'] ?? null;
            if (! is_string($partnerId) || $partnerId === '' || ! $byId->has($partnerId)) {
                return $item;
            }

            /** @var array<string, mixed> $partner */
            $partner = $byId->get($partnerId);
            $item['documents'] = $this->mergeDocuments(
                $item['documents'] ?? [],
                $partner['documents'] ?? [],
            );

            return $item;
        })->values();
    }

    /**
     * @param  list<array<string, mixed>>|mixed  $primary
     * @param  list<array<string, mixed>>|mixed  $secondary
     * @return list<array<string, mixed>>
     */
    private function mergeDocuments(mixed $primary, mixed $secondary): array
    {
        $primaryDocs = is_array($primary) ? $primary : [];
        $secondaryDocs = is_array($secondary) ? $secondary : [];

        if ($secondaryDocs === []) {
            return array_values($primaryDocs);
        }

        $seen = [];
        $merged = [];

        foreach (array_merge($primaryDocs, $secondaryDocs) as $document) {
            if (! is_array($document)) {
                continue;
            }

            $id = $document['id'] ?? null;
            $key = is_int($id) || is_string($id) ? (string) $id : md5(json_encode($document) ?: uniqid('', true));

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $merged[] = $document;
        }

        return $merged;
    }
}

<?php

declare(strict_types=1);

namespace Src\Domain\Process\Services;

use Illuminate\Support\Collection;

class GroupProcessActionsService
{
    /**
     * Etiqueta actuaciones relacionadas de la Rama Judicial.
     * Soporta múltiples formatos de datos (ProcessAction list, Notification Digest data, API Judicial).
     */
    public function handle(Collection $actions): Collection
    {
        if ($actions->isEmpty()) {
            return $actions;
        }

        // 1. Agrupamos por radicado para evitar falsos positivos
        $groupedByProcess = $actions->groupBy(fn (array $action): string => (string) ($action['process_number'] ?? $action['llaveProceso'] ?? 'unknown'));

        $finalCollection = collect();

        foreach ($groupedByProcess as $processGroup) {
            $taggedGroup = $this->tagProcessGroup($processGroup);
            foreach ($taggedGroup as $item) {
                $finalCollection->push($item);
            }
        }

        return $finalCollection;
    }

    private function tagProcessGroup(Collection $actions): array
    {
        // Ordenamos por consecutivo DESC
        $items = $actions->sortByDesc(fn ($a): int => (int) ($a['cons_action'] ?? $a['consActuacion'] ?? 0))->values()->all();
        $count = count($items);

        for ($i = 0; $i < $count; $i++) {
            $current = &$items[$i];

            $actionText = strtolower((string) ($current['action'] ?? $current['action_text'] ?? $current['actuacion'] ?? ''));

            // Buscamos si el siguiente elemento en la lista es su providencia relacionada
            if (str_contains($actionText, 'fijacion estado') && isset($items[$i + 1])) {
                $next = &$items[$i + 1];
                $fijCons = (int) ($current['cons_action'] ?? $current['consActuacion'] ?? 0);
                $nextCons = (int) ($next['cons_action'] ?? $next['consActuacion'] ?? 0);
                /**
                 * FLEXIBILIZACIÓN:
                 * A veces la Rama Judicial se salta consecutivos (ej: 75495219 y 75495217).
                 * Si son vecinos inmediatos en la lista y la diferencia es pequeña (<= 2),
                 * asumimos que son pareja.
                 */
                $diff = $fijCons - $nextCons;
                if ($fijCons > 0 && $diff > 0 && $diff <= 2) {
                    $currentId = $current['id'] ?? $current['process_action_id'] ?? $current['idRegActuacion'] ?? null;
                    $nextId = $next['id'] ?? $next['process_action_id'] ?? $next['idRegActuacion'] ?? null;

                    if ($currentId && $nextId) {
                        $current['notified_action_id'] = $nextId;
                        $next['fijacion_action_id'] = $currentId;
                    }
                }
            }
        }

        return $items;
    }
}

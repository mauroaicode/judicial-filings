<?php

declare(strict_types=1);

namespace Src\Domain\Process\Services;

use Illuminate\Support\Collection;

class GroupProcessActionsService
{
    /**
     * Etiqueta actuaciones relacionadas de la Rama Judicial.
     * Versión ultrarrobusta compatible con arrays y objetos.
     */
    public function handle(Collection $actions): Collection
    {
        if ($actions->isEmpty()) {
            return $actions;
        }

        // Trabajamos con el array subyacente de la colección
        $items = $actions->all();
        
        // 1. Agrupamos índices por radicado
        $processGroups = [];
        foreach ($items as $idx => $item) {
            $radicado = (string) data_get($item, 'process_number', data_get($item, 'llaveProceso', 'unknown'));
            $processGroups[$radicado][] = $idx;
        }

        foreach ($processGroups as $indices) {
            $this->tagIndices($items, $indices);
        }

        return collect($items);
    }

    private function tagIndices(array &$items, array $indices): void
    {
        // Ordenamos los índices del grupo por consecutivo DESC
        usort($indices, function($aIdx, $bIdx) use ($items) {
            $aCons = (int) data_get($items[$aIdx], 'cons_action', data_get($items[$aIdx], 'consActuacion', 0));
            $bCons = (int) data_get($items[$bIdx], 'cons_action', data_get($items[$bIdx], 'consActuacion', 0));
            return $bCons <=> $aCons;
        });

        $count = count($indices);

        for ($i = 0; $i < $count; $i++) {
            $currIdx = $indices[$i];
            $actionText = (string) data_get($items[$currIdx], 'action', data_get($items[$currIdx], 'action_text', data_get($items[$currIdx], 'actuacion', '')));
            
            // Detección robusta de "Fijación Estado" (ignorando tildes, casos y espacios múltiples)
            if ($this->isFijacionEstado($actionText)) {
                $fijCons = (int) data_get($items[$currIdx], 'cons_action', data_get($items[$currIdx], 'consActuacion', 0));
                
                for ($j = $i + 1; $j < $count; $j++) {
                    $nextIdx = $indices[$j];
                    $nextCons = (int) data_get($items[$nextIdx], 'cons_action', data_get($items[$nextIdx], 'consActuacion', 0));

                    if ($fijCons === $nextCons) {
                        continue; // Saltamos duplicados de la misma actuación
                    }

                    $diff = $fijCons - $nextCons;

                    // Si la diferencia es pequeña (hasta 5), los vinculamos
                    if ($fijCons > 0 && $diff > 0 && $diff <= 5) {
                        $fijId = data_get($items[$currIdx], 'id', data_get($items[$currIdx], 'process_action_id', data_get($items[$currIdx], 'idRegActuacion')));
                        $autoId = data_get($items[$nextIdx], 'id', data_get($items[$nextIdx], 'process_action_id', data_get($items[$nextIdx], 'idRegActuacion')));

                        if ($fijId && $autoId) {
                            data_set($items[$currIdx], 'notified_action_id', $autoId);
                            data_set($items[$nextIdx], 'fijacion_action_id', $fijId);
                        }
                    }
                    break; // Solo intentamos vincular con el primer candidato distinto
                }
            }
        }
    }

    private function isFijacionEstado(string $text): bool
    {
        $normalized = mb_strtolower($text, 'UTF-8');
        // Quitamos tildes básicas para la comparación
        $normalized = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $normalized);
        
        return str_contains($normalized, 'fijacion') && str_contains($normalized, 'estado');
    }
}

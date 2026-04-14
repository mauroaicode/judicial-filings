<?php

declare(strict_types=1);

namespace Src\Domain\Process\Services;

use Illuminate\Support\Collection;

class GroupProcessActionsService
{
    /**
     * Etiqueta actuaciones relacionadas de la Rama Judicial (Fijaciones/Autos)
     * buscando en toda la colección para inyectar notified_action_id y fijacion_action_id.
     */
    public function handle(Collection $actions): Collection
    {
        $data = $actions->toArray();
        $count = count($data);

        for ($i = 0; $i < $count; $i++) {
            // Si no es una Fijación o ya tiene pareja, seguimos
            if (! $this->isFijacion($data[$i]) || isset($data[$i]['notified_action_id'])) {
                continue;
            }

            // Buscamos su Auto correspondiente en el resto de la lista
            for ($j = 0; $j < $count; $j++) {
                if ($i === $j) continue;

                if ($this->isActionPair($data[$i], $data[$j])) {
                    // Extraemos los IDs (soportando varios nombres de campo por compatibilidad)
                    $fijId = $data[$i]['process_action_id'] ?? $data[$i]['id'] ?? null;
                    $autoId = $data[$j]['process_action_id'] ?? $data[$j]['id'] ?? null;

                    if ($fijId && $autoId) {
                        $data[$i]['notified_action_id'] = $autoId;
                        $data[$j]['fijacion_action_id'] = $fijId;
                        break; 
                    }
                }
            }
        }

        return collect($data);
    }

    private function isFijacion(array $action): bool
    {
        $text = strtolower($action['action_text'] ?? $action['action'] ?? '');
        return str_contains($text, 'fijacion') || str_contains($text, 'fijación');
    }

    private function isActionPair(array $action1, array $action2): bool
    {
        // 1. Deben ser del mismo proceso
        $rad1 = $action1['process_number'] ?? '';
        $rad2 = $action2['process_number'] ?? '';
        if ($rad1 === '' || $rad1 !== $rad2) {
            return false;
        }

        // 2. Uno debe ser Fijación y el otro Auto o similar
        $text1 = strtolower($action1['action_text'] ?? $action1['action'] ?? '');
        $text2 = strtolower($action2['action_text'] ?? $action2['action'] ?? '');

        $isFij1 = str_contains($text1, 'fijacion') || str_contains($text1, 'fijación');
        $isFij2 = str_contains($text2, 'fijacion') || str_contains($text2, 'fijación');

        if ($isFij1 === $isFij2) {
            return false; 
        }

        // 3. El que NO es fijación debe ser Auto/Sentencia/Decide
        $otherText = $isFij1 ? $text2 : $text1;
        $isAutoOk = str_contains($otherText, 'auto') || 
                    str_contains($otherText, 'sentencia') || 
                    str_contains($otherText, 'decide') ||
                    str_contains($otherText, 'requiere') ||
                    str_contains($otherText, 'reconoce');

        if (! $isAutoOk) {
            return false;
        }

        // 4. Verificación por cons_action si están disponibles (tolerancia de 2)
        $cons1 = (int) ($action1['cons_action'] ?? 0);
        $cons2 = (int) ($action2['cons_action'] ?? 0);

        if ($cons1 > 0 && $cons2 > 0) {
            return abs($cons1 - $cons2) <= 2;
        }

        return true;
    }
}

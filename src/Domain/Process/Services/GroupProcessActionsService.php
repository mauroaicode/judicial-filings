<?php

declare(strict_types=1);

namespace Src\Domain\Process\Services;

use Illuminate\Support\Collection;

class GroupProcessActionsService
{
    /**
     * Pair fijaciones with their corresponding autos.
     *
     * @param  Collection  $actions  The actions to pair and return.
     * @param  Collection|null  $context  Extra actions used only as pairing context (not returned).
     *                                    Pass the items from the next page to enable cross-page pairing.
     */
    public function handle(Collection $actions, ?Collection $context = null): Collection
    {
        if ($context instanceof \Illuminate\Support\Collection && $context->isNotEmpty()) {
            // Merge primary + context for pairing, then strip context from output
            $merged = $actions->merge($context)->values();
            $data = $merged->toArray();
            $count = count($data);

            $this->processPairing($data, $count, true);
            $this->processPairing($data, $count, false);

            // Keep only the original primary items (identified by their array position)
            $primaryCount = $actions->count();
            $primary = array_slice($data, 0, $primaryCount);

            return collect($primary);
        }

        $data = $actions->toArray();
        $count = count($data);

        // Realizamos el proceso en dos pases para manejar prioridades
        // Pase 1: Alta prioridad (Fijaciones/Estados oficiales)
        $this->processPairing($data, $count, true);

        // Pase 2: Baja prioridad (Envíos de notificación / Comunicaciones)
        $this->processPairing($data, $count, false);

        return collect($data);
    }

    /**
     * Procesa el emparejamiento basándose en prioridad.
     */
    private function processPairing(array &$data, int $count, bool $highPriorityOnly): void
    {
        for ($i = 0; $i < $count; $i++) {
            // Saltamos si ya tiene pareja
            if (isset($data[$i]['notified_action_id'])) {
                continue;
            }

            // Validamos si es una Fijación/Notificación
            if (! $this->isFijacion($data[$i])) {
                continue;
            }

            // Si estamos en modo alta prioridad, solo procesamos estados/fijaciones
            if ($highPriorityOnly && ! $this->isHighPriorityFijacion($data[$i])) {
                continue;
            }

            // Buscamos su Auto correspondiente
            for ($j = 0; $j < $count; $j++) {
                if ($i === $j) {
                    continue;
                }

                // El Auto NO debe estar ya reclamado por otra notificación
                if (isset($data[$j]['fijacion_action_id'])) {
                    continue;
                }

                if ($this->isActionPair($data[$i], $data[$j])) {
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
    }

    private function isHighPriorityFijacion(array $action): bool
    {
        $text = strtolower($action['action_text'] ?? $action['action'] ?? '');

        return str_contains($text, 'estado') || str_contains($text, 'fijacion') || str_contains($text, 'fijación');
    }

    private function isFijacion(array $action): bool
    {
        $text = strtolower($action['action_text'] ?? $action['action'] ?? '');

        return str_contains($text, 'fijacion') ||
               str_contains($text, 'fijación') ||
               str_contains($text, 'notificacion') ||
               str_contains($text, 'notificación') ||
               str_contains($text, 'publicacion estado') ||
               str_contains($text, 'publicación estado');
    }

    private function isActionPair(array $action1, array $action2): bool
    {
        // 1. Deben ser del mismo proceso
        $rad1 = $action1['process_number'] ?? '';
        $rad2 = $action2['process_number'] ?? '';
        if ($rad1 === '' || $rad1 !== $rad2) {
            return false;
        }

        // 2. Uno debe ser Fijación/Notificación y el otro Auto o similar
        $text1 = strtolower($action1['action_text'] ?? $action1['action'] ?? '');
        $text2 = strtolower($action2['action_text'] ?? $action2['action'] ?? '');

        $isFij1 = $this->isFijacion($action1);
        $isFij2 = $this->isFijacion($action2);

        if ($isFij1 === $isFij2) {
            return false;
        }

        // 3. El que NO es fijación debe ser Auto/Sentencia/Decide/etc.
        $otherText = $isFij1 ? $text2 : $text1;
        $fijAction = $isFij1 ? $action1 : $action2;

        $isAutoOk = str_contains($otherText, 'auto') ||
                    str_contains($otherText, 'sentencia') ||
                    str_contains($otherText, 'decide') ||
                    str_contains($otherText, 'requiere') ||
                    str_contains($otherText, 'reconoce') ||
                    str_contains($otherText, 'admite') ||
                    str_contains($otherText, 'resolución') ||
                    str_contains($otherText, 'resolucion') ||
                    str_contains($otherText, 'rechaza') ||
                    str_contains($otherText, 'niega') ||
                    str_contains($otherText, 'ordena') ||
                    str_contains($otherText, 'decreta') ||
                    str_contains($otherText, 'inadmite') ||
                    str_contains($otherText, 'condena');

        if (! $isAutoOk) {
            return false;
        }

        // 4. Verificación por Contenido (Fuzzy match de alta prioridad)
        // En Colombia, la "Notificación" suele decir qué Auto está notificando en su anotación.
        $fijAnnotation = strtolower($fijAction['annotation'] ?? '');
        if ($fijAnnotation !== '' && str_contains($fijAnnotation, $otherText)) {
            return true;
        }

        // 5. Verificación por cons_action si están disponibles (tolerancia aumentada a 10)
        $cons1 = (int) ($action1['cons_action'] ?? 0);
        $cons2 = (int) ($action2['cons_action'] ?? 0);

        if ($cons1 > 0 && $cons2 > 0 && abs($cons1 - $cons2) > 10) {
            return false;
        }

        // 6. Deben ser relativamente cercanos en fecha (máximo 30 días para Notificaciones)
        $date1Str = $action1['registration_date'] ?? null;
        $date2Str = $action2['registration_date'] ?? null;

        if ($date1Str && $date2Str) {
            try {
                $d1 = \Illuminate\Support\Facades\Date::parse($date1Str);
                $d2 = \Illuminate\Support\Facades\Date::parse($date2Str);

                if ($d1->diffInDays($d2) > 30) {
                    return false;
                }
            } catch (\Throwable) {
                // Si hay error en fechas, ignoramos este filtro pero seguimos
            }
        }

        return true;
    }
}

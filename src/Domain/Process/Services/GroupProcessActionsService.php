<?php

declare(strict_types=1);

namespace Src\Domain\Process\Services;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

class GroupProcessActionsService
{
    /**
     * Pair fijaciones with their corresponding autos.
     *
     * A pair is only valid on the same calendar day. Exclusive 1-to-1 matching
     * runs first (annotation, then cons_action). Leftover same-day autos then
     * attach to that day's fijación so one estado can cover several autos.
     * Leftover autos never claim a fijación from another day.
     *
     * @param  Collection  $actions  The actions to pair and return.
     * @param  Collection|null  $context  Extra actions used only as pairing context (not returned).
     *                                    Pass the items from the next page to enable cross-page pairing.
     */
    public function handle(Collection $actions, ?Collection $context = null): Collection
    {
        if ($context instanceof Collection && $context->isNotEmpty()) {
            $merged = $actions->merge($context)->values();
            $data = $merged->toArray();
            $count = count($data);

            $this->processPairing($data, $count, true);
            $this->processPairing($data, $count, false);
            $this->attachLeftoverSameDayAutos($data, $count);

            $primaryCount = $actions->count();
            $primary = array_slice($data, 0, $primaryCount);

            return collect($primary);
        }

        $data = $actions->toArray();
        $count = count($data);

        $this->processPairing($data, $count, true);
        $this->processPairing($data, $count, false);
        $this->attachLeftoverSameDayAutos($data, $count);

        return collect($data);
    }

    /**
     * Pairing plus consolidado rows: one Fijación+Auto line per same-day auto.
     *
     * Process detail uses {@see handle()} (badge on each auto). Actuaciones recientes
     * and the email need {@see handleForConsolidado()} so leftover autos are not
     * shown as unpaired.
     */
    public function handleForConsolidado(Collection $actions): Collection
    {
        return $this->expandFijacionesToOneRowPerAuto($this->handle($actions));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $tagged
     * @return Collection<int, array<string, mixed>>
     */
    private function expandFijacionesToOneRowPerAuto(Collection $tagged): Collection
    {
        $claimedAutoIds = [];
        $rows = collect();

        foreach ($tagged as $item) {
            $itemId = $item['process_action_id'] ?? $item['id'] ?? null;
            $linkedAutos = $tagged
                ->filter(fn (array $candidate): bool => $itemId !== null
                    && ($candidate['fijacion_action_id'] ?? null) === $itemId)
                ->values();

            $alreadyMerged = ! empty($item['is_merged']);

            if ($linkedAutos->isEmpty() && ! $alreadyMerged && isset($item['notified_action_id'])) {
                $auto = $tagged->firstWhere('process_action_id', $item['notified_action_id']);
                if (is_array($auto)) {
                    $linkedAutos = collect([$auto]);
                }
            }

            if ($alreadyMerged) {
                $rows->push($item);
                $notifiedId = $item['notified_action_id'] ?? null;
                if (is_string($notifiedId) && $notifiedId !== '') {
                    $claimedAutoIds[] = $notifiedId;
                }

                foreach ($linkedAutos as $auto) {
                    $autoId = $auto['process_action_id'] ?? $auto['id'] ?? null;
                    if ($autoId === $notifiedId) {
                        continue;
                    }
                    if (is_string($autoId) && $autoId !== '') {
                        $claimedAutoIds[] = $autoId;
                    }
                    $rows->push($this->mergeFijacionWithAuto($item, $auto, keepFijacionProcessActionId: false));
                }

                continue;
            }

            if ($linkedAutos->isEmpty()) {
                $rows->push($item);

                continue;
            }

            foreach ($linkedAutos as $index => $auto) {
                $autoId = $auto['process_action_id'] ?? $auto['id'] ?? null;
                if (is_string($autoId) && $autoId !== '') {
                    $claimedAutoIds[] = $autoId;
                }
                $rows->push($this->mergeFijacionWithAuto($item, $auto, keepFijacionProcessActionId: $index === 0));
            }
        }

        return $rows->reject(function (array $item) use ($claimedAutoIds): bool {
            if (! empty($item['is_merged'])) {
                return false;
            }

            $id = $item['process_action_id'] ?? $item['id'] ?? null;

            return is_string($id) && in_array($id, $claimedAutoIds, true);
        })->values();
    }

    /**
     * @param  array<string, mixed>  $fijacion
     * @param  array<string, mixed>  $auto
     * @return array<string, mixed>
     */
    private function mergeFijacionWithAuto(array $fijacion, array $auto, bool $keepFijacionProcessActionId): array
    {
        $fijacionId = $fijacion['process_action_id'] ?? $fijacion['id'] ?? null;
        $autoId = $auto['process_action_id'] ?? $auto['id'] ?? null;

        $fijacion['is_merged'] = true;
        $fijacion['linked_action_text'] = $auto['action_text'] ?? $auto['action'] ?? null;
        $fijacion['linked_annotation'] = $auto['annotation'] ?? null;
        $fijacion['notified_action_id'] = $autoId;
        $fijacion['is_alert'] = ($fijacion['is_alert'] ?? false) || ($auto['is_alert'] ?? false);
        $fijacion['matched_keywords'] = collect([$fijacion['matched_keywords'] ?? null, $auto['matched_keywords'] ?? null])
            ->filter()
            ->unique()
            ->implode(', ');

        if (! $keepFijacionProcessActionId) {
            $fijacion['process_action_id'] = $autoId ?? $fijacionId;
        }

        return $fijacion;
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     */
    private function processPairing(array &$data, int $count, bool $highPriorityOnly): void
    {
        for ($i = 0; $i < $count; $i++) {
            if (isset($data[$i]['notified_action_id'])) {
                continue;
            }

            if (! $this->isFijacion($data[$i])) {
                continue;
            }

            if ($highPriorityOnly && ! $this->isHighPriorityFijacion($data[$i])) {
                continue;
            }

            $bestIndex = null;
            $bestScore = null;

            for ($j = 0; $j < $count; $j++) {
                if ($i === $j) {
                    continue;
                }

                if (isset($data[$j]['fijacion_action_id'])) {
                    continue;
                }

                if (! $this->isEligiblePair($data[$i], $data[$j])) {
                    continue;
                }

                $score = $this->pairScore($data[$i], $data[$j]);
                if ($bestScore === null || $score > $bestScore) {
                    $bestScore = $score;
                    $bestIndex = $j;
                }
            }

            if ($bestIndex === null) {
                continue;
            }

            $fijId = $data[$i]['process_action_id'] ?? $data[$i]['id'] ?? null;
            $autoId = $data[$bestIndex]['process_action_id'] ?? $data[$bestIndex]['id'] ?? null;

            if ($fijId && $autoId) {
                $this->linkFijacionToAuto($data[$i], $data[$bestIndex], $fijId, $autoId);
            }
        }
    }

    /**
     * After exclusive pairs, bind leftover same-day autos to that day's fijación.
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    private function attachLeftoverSameDayAutos(array &$data, int $count): void
    {
        for ($j = 0; $j < $count; $j++) {
            if (isset($data[$j]['fijacion_action_id'])) {
                continue;
            }

            if ($this->isFijacion($data[$j])) {
                continue;
            }

            $otherText = strtolower((string) ($data[$j]['action_text'] ?? $data[$j]['action'] ?? ''));
            if (! $this->looksLikeLinkedDecision($otherText)) {
                continue;
            }

            $bestIndex = null;
            $bestScore = null;

            for ($i = 0; $i < $count; $i++) {
                if ($i === $j) {
                    continue;
                }

                if (! $this->isFijacion($data[$i])) {
                    continue;
                }

                if (! $this->isEligiblePair($data[$i], $data[$j])) {
                    continue;
                }

                $score = $this->pairScore($data[$i], $data[$j]);
                if ($bestScore === null || $score > $bestScore) {
                    $bestScore = $score;
                    $bestIndex = $i;
                }
            }

            if ($bestIndex === null) {
                continue;
            }

            $fijId = $data[$bestIndex]['process_action_id'] ?? $data[$bestIndex]['id'] ?? null;
            $autoId = $data[$j]['process_action_id'] ?? $data[$j]['id'] ?? null;

            if ($fijId && $autoId) {
                $this->linkFijacionToAuto($data[$bestIndex], $data[$j], (string) $fijId, (string) $autoId);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $fijacion
     * @param  array<string, mixed>  $auto
     */
    private function linkFijacionToAuto(array &$fijacion, array &$auto, string $fijId, string $autoId): void
    {
        $auto['fijacion_action_id'] = $fijId;

        $ids = $fijacion['notified_action_ids'] ?? [];
        if (! is_array($ids)) {
            $ids = [];
        }

        if (! in_array($autoId, $ids, true)) {
            $ids[] = $autoId;
        }

        $fijacion['notified_action_ids'] = $ids;
        $fijacion['notified_action_id'] ??= $autoId;
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

    private function isEligiblePair(array $action1, array $action2): bool
    {
        $rad1 = $action1['process_number'] ?? '';
        $rad2 = $action2['process_number'] ?? '';
        if ($rad1 === '' || $rad1 !== $rad2) {
            return false;
        }

        $isFij1 = $this->isFijacion($action1);
        $isFij2 = $this->isFijacion($action2);

        if ($isFij1 === $isFij2) {
            return false;
        }

        $other = $isFij1 ? $action2 : $action1;
        $otherText = strtolower($other['action_text'] ?? $other['action'] ?? '');

        if (! $this->looksLikeLinkedDecision($otherText)) {
            return false;
        }

        $date1 = $this->resolveDate($action1);
        $date2 = $this->resolveDate($action2);

        if (! $date1 instanceof \Illuminate\Support\Carbon || ! $date2 instanceof \Illuminate\Support\Carbon) {
            return false;
        }

        return $date1->isSameDay($date2);
    }

    /**
     * Higher is better. Prefer annotation/content match, then closer cons_action.
     *
     * @param  array<string, mixed>  $fijacion
     * @param  array<string, mixed>  $auto
     */
    private function pairScore(array $fijacion, array $auto): int
    {
        $score = 0;

        $autoText = strtolower(trim((string) ($auto['action_text'] ?? $auto['action'] ?? '')));
        $fijAnnotation = strtolower(trim((string) ($fijacion['annotation'] ?? '')));

        if ($autoText !== '' && $fijAnnotation !== '' && str_contains($fijAnnotation, $autoText)) {
            $score += 1000;
        }

        $cons1 = (int) ($fijacion['cons_action'] ?? 0);
        $cons2 = (int) ($auto['cons_action'] ?? 0);

        if ($cons1 > 0 && $cons2 > 0) {
            $score += max(0, 100 - abs($cons1 - $cons2));
        }

        return $score;
    }

    private function looksLikeLinkedDecision(string $text): bool
    {
        foreach ([
            'auto',
            'sentencia',
            'edicto',
            'traslado',
            'decide',
            'requiere',
            'reconoce',
            'admite',
            'resolución',
            'resolucion',
            'rechaza',
            'niega',
            'ordena',
            'decreta',
            'inadmite',
            'condena',
        ] as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function resolveDate(array $action): ?Carbon
    {
        foreach (['registration_date_iso', 'action_date_iso', 'registration_date', 'action_date'] as $key) {
            $parsed = $this->parseDate($action[$key] ?? null);
            if ($parsed instanceof Carbon) {
                return $parsed;
            }
        }

        return null;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if ($value instanceof DateTimeInterface) {
            return Date::instance(\DateTimeImmutable::createFromInterface($value))->startOfDay();
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || in_array($trimmed, ['-', '---', '—', 'n/a', 'N/A'], true)) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $trimmed) === 1) {
            try {
                return Date::parse($trimmed)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $trimmed) === 1) {
            $parsed = Date::createFromFormat('d/m/Y', $trimmed);

            return $parsed instanceof Carbon ? $parsed->startOfDay() : null;
        }

        return $this->parseSpanishLongDate($trimmed);
    }

    private function parseSpanishLongDate(string $value): ?Carbon
    {
        if (preg_match('/^(\d{1,2})\s+de\s+(\p{L}+)\s+de\s+(\d{4})/ui', $value, $matches) !== 1) {
            return null;
        }

        $months = [
            'enero' => 1,
            'febrero' => 2,
            'marzo' => 3,
            'abril' => 4,
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'octubre' => 10,
            'noviembre' => 11,
            'diciembre' => 12,
        ];

        $month = $months[mb_strtolower($matches[2])] ?? null;
        if ($month === null) {
            return null;
        }

        $day = (int) $matches[1];
        $year = (int) $matches[3];

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return Date::create($year, $month, $day)->startOfDay();
    }
}

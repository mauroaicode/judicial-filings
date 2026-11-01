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
     * A pair is only valid on the same calendar day. When several autos share
     * one fijación (Excel “Fijación Estado Auto …” split), leftover autos stay
     * unpaired instead of claiming an older fijación.
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

            $primaryCount = $actions->count();
            $primary = array_slice($data, 0, $primaryCount);

            return collect($primary);
        }

        $data = $actions->toArray();
        $count = count($data);

        $this->processPairing($data, $count, true);
        $this->processPairing($data, $count, false);

        return collect($data);
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
                $data[$i]['notified_action_id'] = $autoId;
                $data[$bestIndex]['fijacion_action_id'] = $fijId;
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

        if ($date1 === null || $date2 === null) {
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

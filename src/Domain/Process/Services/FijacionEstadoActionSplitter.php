<?php

declare(strict_types=1);

namespace Src\Domain\Process\Services;

/**
 * Publicaciones Procesales / Excel often concatena "Fijación Estado" + "Auto …" en un solo texto.
 * En Rama Judicial llegan como dos actuaciones; {@see GroupProcessActionsService} las empareja
 * solo si existen como filas separadas.
 */
final class FijacionEstadoActionSplitter
{
    /**
     * @return list<string> One title, or [estadoLabel, decisionLabel] when split applies.
     */
    public function split(string $actionText): array
    {
        $trimmed = trim((string) preg_replace('/\s+/u', ' ', $actionText));
        if ($trimmed === '') {
            return [];
        }

        if (preg_match(
            '/^(fijaci[oó]n\s+estado|notificaci[oó]n\s+estado|publicaci[oó]n\s+estado)\s+(.+)$/iu',
            $trimmed,
            $matches
        ) !== 1) {
            return [$trimmed];
        }

        $rest = trim($matches[2]);
        if ($rest === '' || ! $this->looksLikeLinkedDecision($rest)) {
            return [$trimmed];
        }

        return [
            $this->canonicalEstadoLabel($matches[1]),
            $rest,
        ];
    }

    private function looksLikeLinkedDecision(string $text): bool
    {
        $lower = mb_strtolower($text);

        foreach ([
            'auto',
            'sentencia',
            'decide',
            'requiere',
            'reconoce',
            'admite',
            'resoluci',
            'rechaza',
            'niega',
            'ordena',
            'decreta',
            'inadmite',
            'condena',
        ] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the title is only the estado half of a split pair
     * (Fijación / Notificación / Publicación Estado), not a decision Auto.
     */
    public function isEstadoPairLabel(string $actionText): bool
    {
        $normalized = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $actionText)));

        return in_array($normalized, [
            'fijación estado',
            'fijacion estado',
            'notificación estado',
            'notificacion estado',
            'publicación estado',
            'publicacion estado',
        ], true);
    }

    private function canonicalEstadoLabel(string $prefix): string
    {
        $lower = mb_strtolower($prefix);

        if (str_contains($lower, 'notific')) {
            return 'Notificación Estado';
        }

        if (str_contains($lower, 'public')) {
            return 'Publicación Estado';
        }

        return 'Fijación Estado';
    }
}

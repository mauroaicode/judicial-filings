<?php

declare(strict_types=1);

namespace Src\Application\Shared\Contracts\Alert;

interface AnnotationAlertDetectionInterface
{
    /**
     * Determine if the annotation text contains alert keywords (e.g. CONSULTA, APELACIÓN).
     */
    public function containsAlertKeywords(string $annotation): bool;

    /**
     * Return detected alert spans (start, end, text) in the text (e.g. annotation + action).
     *
     * @return array<int, array{start: int, end: int, text: string}>
     */
    public function getDetectedAlertSpans(string $text): array;
}

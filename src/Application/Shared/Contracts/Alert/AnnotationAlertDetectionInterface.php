<?php

declare(strict_types=1);

namespace Src\Application\Shared\Contracts\Alert;

interface AnnotationAlertDetectionInterface
{
    /**
     * Determine if the annotation text contains alert keywords (e.g. CONSULTA, APELACIÓN).
     */
    public function containsAlertKeywords(string $annotation): bool;
}

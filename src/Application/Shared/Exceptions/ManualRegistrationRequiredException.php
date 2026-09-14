<?php

declare(strict_types=1);

namespace Src\Application\Shared\Exceptions;

use Exception;
use Src\Domain\Process\Enums\ManualRegistrationRequestReason;
use Src\Domain\Process\Enums\ProcessLawyerRole;

/**
 * El radicado no puede darse de alta por consulta automática (privado / no encontrado).
 * El caller debe crear una solicitud manual y responder al cliente con HTTP 202.
 */
class ManualRegistrationRequiredException extends Exception
{
    public function __construct(
        public readonly string $processNumber,
        public readonly ManualRegistrationRequestReason $reason,
        public readonly ?ProcessLawyerRole $lawyerRole = null,
    ) {
        parent::__construct($reason->label());
    }
}

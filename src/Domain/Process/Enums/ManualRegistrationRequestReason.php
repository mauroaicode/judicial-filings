<?php

declare(strict_types=1);

namespace Src\Domain\Process\Enums;

enum ManualRegistrationRequestReason: string
{
    case NotFound = 'not_found';
    case Private = 'private';
    case AllPrivate = 'all_private';

    public function label(): string
    {
        return match ($this) {
            self::NotFound => 'No encontrado en consulta automática',
            self::Private => 'Proceso privado',
            self::AllPrivate => 'Todas las instancias privadas',
        };
    }
}
